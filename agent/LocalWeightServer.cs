using System;
using System.Net;
using System.Text;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace DFAgent;

/// <summary>
/// Phục vụ số cân cho trình duyệt chạy trên CHÍNH máy trạm này — đường nhanh của ADR-013.
///
/// Vấn đề nó giải: Agent đọc cân mỗi 10ms nhưng con số phải đi hai chặng mạng và chờ hai lần
/// nhịp (Agent đẩy 200ms + trình duyệt hỏi backend 200ms) mới lên tới màn hình, tổng 400-900ms.
/// VBA đọc cùng file log đó, cùng máy, ghi thẳng vào form. Đường này bỏ cả hai chặng: trình
/// duyệt lấy thẳng bản chụp trong bộ nhớ Agent, còn ~50ms.
///
/// KHÔNG thay thế đường cũ. Agent vẫn đẩy số lên backend y như trước (audit, các màn hình khác,
/// hàng đợi offline); trình duyệt tự rơi về đường backend khi không gọi được cổng này.
///
/// ====================== BA ĐIỂM BẮT BUỘC, ĐỪNG "DỌN GỌN" ======================
/// 1. CHỈ nghe `127.0.0.1`. Endpoint này không có xác thực; nó an toàn ĐÚNG VÌ ngoài mạng không
///    ai tới được. Đổi sang `+` hay `0.0.0.0` là mở số cân của trạm cho cả phân xưởng.
/// 2. Phải trả `Access-Control-Allow-Private-Network: true` cho preflight. Thợ mở màn hình bằng
///    `http://10.0.60.209:3001` (IP riêng) rồi gọi xuống loopback là request "xuyên vùng mạng"
///    theo luật Private Network Access của Chrome — thiếu header này thì Chrome chặn, đường cục
///    bộ chết câm và màn hình lặng lẽ tụt về đường backend chậm.
/// 3. Cổng cố định theo loại cân (SMALL 8770 / LARGE 8771). Frontend không đọc được
///    `appsettings.json` của Agent nên đây là quy ước, đổi thì phải đổi cả `useScaleFeed.ts`.
/// </summary>
public class LocalWeightServer : BackgroundService
{
    /// <summary>Cổng mặc định theo loại cân — phải khớp `CONG_AGENT_CUC_BO` trong frontend/src/composables/useScaleFeed.ts.</summary>
    private const int CongMacDinhSmall = 8770;
    private const int CongMacDinhLarge = 8771;

    private readonly ILogger<LocalWeightServer> _logger;
    private readonly ScaleSnapshot _snapshot;
    private readonly bool _enabled;
    private readonly int _port;
    private readonly string _workstationId;
    private readonly string _scaleKind;

    public LocalWeightServer(
        ILogger<LocalWeightServer> logger,
        IConfiguration config,
        ScaleSnapshot snapshot)
    {
        _logger = logger;
        _snapshot = snapshot;

        _scaleKind = Worker.ResolveScaleKind(config);
        _workstationId = Worker.ResolveWorkstationId(config);
        // Không đọc cân thì KHÔNG mở cổng, bất kể cấu hình nói gì — xem Worker.ScaleEnabled().
        _enabled = Worker.ScaleEnabled(config) && config.GetValue<bool>("Local:Enabled", true);
        _port = config.GetValue<int>("Local:Port", _scaleKind == "LARGE" ? CongMacDinhLarge : CongMacDinhSmall);
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        if (!_enabled)
        {
            _logger.LogInformation("Duong can cuc bo TAT theo cau hinh (Local:Enabled=false) — man hinh se doc so cua backend nhu cu.");
            return;
        }

        HttpListener listener;
        try
        {
            listener = new HttpListener();
            listener.Prefixes.Add($"http://127.0.0.1:{_port}/");
            listener.Start();
        }
        catch (Exception ex)
        {
            // Cổng bị chiếm (cài hai bản cùng loại, hoặc ứng dụng khác giữ cổng) KHÔNG được làm
            // chết service: mất đường nhanh thì màn hình vẫn chạy bằng đường backend.
            _logger.LogError("Khong mo duoc duong can cuc bo tren 127.0.0.1:{Port} ({Msg}). Agent van chay binh thuong, man hinh se dung duong backend cham hon.", _port, ex.Message);
            return;
        }

        _logger.LogInformation("Duong can cuc bo: http://127.0.0.1:{Port}/weight (tram {Ws}, loai {Kind})", _port, _workstationId, _scaleKind);

        // GetContextAsync không nhận CancellationToken — đóng listener là cách duy nhất để nó
        // thoát khỏi trạng thái chờ, và nó thoát bằng cách NÉM (đã bắt ở dưới).
        using var dangKyTat = stoppingToken.Register(() =>
        {
            try { listener.Stop(); } catch { /* đang tắt máy, không còn gì để cứu */ }
        });

        while (!stoppingToken.IsCancellationRequested)
        {
            HttpListenerContext ctx;
            try
            {
                ctx = await listener.GetContextAsync();
            }
            catch (Exception) when (stoppingToken.IsCancellationRequested)
            {
                break;  // listener.Stop() ở trên — thoát êm, không phải lỗi
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Duong can cuc bo: loi khi nhan request ({Msg})", ex.Message);
                continue;
            }

            // Không await: một client treo không được phép chặn các request sau. Trả lời một
            // bản chụp trong bộ nhớ thì không có gì để hỏng, nên bắn rồi quên.
            _ = Task.Run(() => TraLoi(ctx), CancellationToken.None);
        }

        try { listener.Close(); } catch { /* đã đóng */ }
    }

    private void TraLoi(HttpListenerContext ctx)
    {
        try
        {
            var res = ctx.Response;

            // CORS: trang web chạy ở origin khác (http://localhost:3001 hoặc http://10.0.60.209:3001).
            // `*` chấp nhận được vì chỉ nghe loopback và chỉ trả một con số cân đọc-được.
            res.AddHeader("Access-Control-Allow-Origin", "*");
            res.AddHeader("Access-Control-Allow-Methods", "GET, OPTIONS");
            res.AddHeader("Access-Control-Allow-Headers", "Content-Type");
            // Xem ghi chú số 2 ở đầu lớp — thiếu dòng này là Chrome chặn khi mở màn bằng IP riêng.
            res.AddHeader("Access-Control-Allow-Private-Network", "true");
            // Số cân đổi mỗi 10ms; để trình duyệt hay proxy nào đó cache lại là hiển thị số chết.
            res.AddHeader("Cache-Control", "no-store");

            if (ctx.Request.HttpMethod == "OPTIONS")
            {
                res.StatusCode = 204;
                res.Close();
                return;
            }

            var duong = ctx.Request.Url?.AbsolutePath ?? "/";
            if (!duong.Equals("/weight", StringComparison.OrdinalIgnoreCase))
            {
                res.StatusCode = 404;
                res.Close();
                return;
            }

            var ban = _snapshot.Doc();

            // Cùng HÌNH DẠNG với DeviceController::getReading để frontend dùng đúng một đoạn xử
            // lý cho cả hai nguồn — lệch một tên trường là sinh ra hai nhánh phải giữ đồng bộ.
            // `has_reading=false` khi chưa đọc được lần nào (PuTTY chưa chạy, cân chưa cắm):
            // giữ weight=0.0 cho khớp bản backend, client phải nhìn has_reading/age_ms.
            var payload = new
            {
                status = "SUCCESS",
                workstation_id = _workstationId,
                weight = ban?.Weight ?? 0.0,
                is_stable = ban?.Stable ?? false,
                has_reading = ban is not null,
                age_ms = ban?.TuoiMs,
                source = "AGENT_LOCAL",
                scale_kind = _scaleKind,
            };

            var body = Encoding.UTF8.GetBytes(JsonSerializer.Serialize(payload));
            res.StatusCode = 200;
            res.ContentType = "application/json; charset=utf-8";
            res.ContentLength64 = body.Length;
            res.OutputStream.Write(body, 0, body.Length);
            res.Close();
        }
        catch (Exception ex)
        {
            // Client đóng kết nối giữa chừng là chuyện thường (đổi trang, F5) — không ồn ào.
            _logger.LogDebug("Duong can cuc bo: khong tra loi duoc mot request ({Msg})", ex.Message);
            try { ctx.Response.Abort(); } catch { /* đã hỏng hẳn */ }
        }
    }
}
