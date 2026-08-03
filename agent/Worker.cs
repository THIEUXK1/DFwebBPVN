using System;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class Worker : BackgroundService
{
    private readonly ILogger<Worker> _logger;
    private readonly IConfiguration _config;
    private readonly ScaleReader _scaleReader;
    private readonly LabelPrinter _labelPrinter;
    private readonly OfflineQueue _offlineQueue;
    private readonly PrinterDiscovery _printerDiscovery;
    private readonly HttpClient _httpClient;
    private readonly string _backendUrl;
    private readonly string[] _backendUrls;
    private readonly string _workstationId;
    private readonly string _role;
    private readonly string _scaleKind;
    private readonly int _readIntervalMs;
    private readonly int _pushIntervalMs;
    private readonly int _printPollIntervalMs;
    private readonly bool _scaleEnabled;
    private readonly bool _printEnabled;
    private readonly RackSender _rackSender;
    private readonly RackOptions _rackOptions;

    // Trạng thái lần đẩy gần nhất của TỪNG backend, chỉ để quyết định có ghi log hay không.
    //
    // Nhịp đẩy là 200ms, nên một backend chết mà cứ mỗi lần hỏng lại ghi một dòng cảnh báo là
    // 5 dòng/giây đổ vào Event Log — trôi mất mọi thứ khác và làm log vô dụng đúng lúc cần đọc
    // nhất. Chỉ ghi khi trạng thái ĐỔI: lúc hỏng lần đầu, và lúc sống lại.
    private readonly System.Collections.Generic.Dictionary<string, bool> _backendOk = new();

    // Danh sách máy in cài sẵn hiếm khi đổi — không cần báo cáo mỗi vòng lặp 500ms
    // như số cân. Báo lại mỗi 60 giây (đủ nhanh để phát hiện máy in mới cắm vào).
    private DateTime _nextPrinterReportAt = DateTime.MinValue;
    private static readonly TimeSpan PrinterReportInterval = TimeSpan.FromSeconds(60);

    public Worker(
        ILogger<Worker> logger,
        IConfiguration config,
        ScaleReader scaleReader,
        LabelPrinter labelPrinter,
        OfflineQueue offlineQueue,
        PrinterDiscovery printerDiscovery,
        RackSender rackSender,
        RackOptions rackOptions)
    {
        _logger = logger;
        _config = config;
        _scaleReader = scaleReader;
        _labelPrinter = labelPrinter;
        _offlineQueue = offlineQueue;
        _printerDiscovery = printerDiscovery;
        _rackSender = rackSender;
        _rackOptions = rackOptions;

        _backendUrls = ResolveBackendUrls(_config);
        // Backend CHINH: dung cho cac viec chi co mot dich den hop ly (lay lenh in, bao cao may
        // in). Chi so can moi duoc day len TAT CA backend — xem PushWeightToBackendAsync.
        _backendUrl = _backendUrls[0];
        _scaleKind = ResolveScaleKind(_config);
        _workstationId = ResolveWorkstationId(_config);
        // NHỊP ĐỌC tách khỏi NHỊP ĐẨY (2026-08-01). Trước đây một giá trị PollIntervalMs điều
        // khiển cả hai, buộc phải đánh đổi giữa độ chính xác và tải mạng — thực ra hai việc này
        // có chi phí hoàn toàn khác nhau:
        //
        //   ĐỌC  (10ms): đọc đuôi file cục bộ / lấy biến đã chốt từ cổng COM — gần như miễn phí,
        //                đúng nhịp VBA StartFastLoop. Đây là nhịp quyết định StableFilter:
        //                "ổn định" = 2 lần đọc liên tiếp giống nhau = 20ms, ĐÚNG BẰNG VBA
        //                (trước đây ở 500ms là 1 GIÂY mới dám báo ổn định).
        //   ĐẨY (200ms): mỗi lần là một HTTP request + một vòng bootstrap Laravel phía backend —
        //                đây mới là thứ đắt, và cũng là thứ duy nhất cần cân nhắc khi nhân số trạm.
        //
        // LƯU Ý TRIỂN KHAI: đổi mặc định ở đây KHÔNG tự áp dụng cho máy đã cài — appsettings.json
        // tại C:\Program Files\DFAgent\ ghi đè. Phải sửa tay hoặc cài lại MSI.
        //
        // PollIntervalMs (tên cũ) vẫn được đọc làm giá trị dự phòng cho nhịp ĐẨY, để cấu hình cũ
        // trên máy đã cài không bị bỏ qua im lặng sau khi cập nhật Agent.
        int legacyPollMs = _config.GetValue<int>("Scale:PollIntervalMs", 200);
        _readIntervalMs = Math.Max(1, _config.GetValue<int>("Scale:ReadIntervalMs", 10));
        _pushIntervalMs = Math.Max(_readIntervalMs, _config.GetValue<int>("Scale:PushIntervalMs", legacyPollMs));

        // Lệnh in không liên quan gì tới nhịp đọc cân — trước đây bị buộc chung vòng lặp, nếu để
        // vòng lặp chạy 10ms mà không tách ra thì sẽ thành 100 request lấy lệnh in mỗi giây.
        _printPollIntervalMs = Math.Max(200, _config.GetValue<int>("Printer:PollIntervalMs", 1000));

        // Role quyet dinh vong lap nao chay — mot may vat ly chi gan 1 loai thiet bi
        // (may in HOAC can), khong can ca 2. Mac dinh BOTH de tuong thich nguoc voi
        // cau hinh cu chua co truong nay. Xem DFAgentSetup.wxs (dropdown chon vai tro
        // luc cai) va session-log.md muc lien quan (2026-07-29).
        _role = _config.GetValue<string>("Workstation:Role", "BOTH") ?? "BOTH";
        _scaleEnabled = _role is "BOTH" or "SCALE_ONLY";
        _printEnabled = _role is "BOTH" or "PRINT_ONLY";

        _httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(5) };

        // Xác thực Agent bằng token workstation (backend middleware AgentAuth) — cùng
        // token đã cấp cho handshake trình duyệt kiosk tại đúng máy này, KHÔNG phải
        // tài khoản người dùng. Xem local-agent-architecture.md Mục 4.2.
        string? workstationToken = _config.GetValue<string>("Workstation:Token");
        if (!string.IsNullOrEmpty(workstationToken))
        {
            _httpClient.DefaultRequestHeaders.Add("X-Workstation-Token", workstationToken);
        }
        else
        {
            _logger.LogWarning("Workstation:Token chưa được cấu hình — backend sẽ từ chối mọi request nếu bật enforcement (agent.auth).");
        }
    }

    /// <summary>
    /// Danh sách backend nhận số cân. Trả về ít nhất 1 phần tử.
    ///
    /// LÝ DO CÓ NHIỀU (2026-08-03): frontend suy ra host của API từ CHÍNH URL trình duyệt đang
    /// mở (`axios.defaults.baseURL = http://<hostname>:8500`, xem frontend/src/main.ts). Nên mở
    /// màn hình cân bằng `localhost:3001` là nó hỏi backend chạy ngay trên máy đó, còn mở bằng
    /// `10.0.60.209:3001` là hỏi backend trên CS-SERVER — HAI kho cache tách rời. Agent chỉ đẩy
    /// vào một kho thì địa chỉ còn lại vĩnh viễn không thấy số cân nào, mà đó lại là trạng thái
    /// "cân chết" không có cách nào phân biệt với hỏng thật.
    ///
    /// Khai báo `Backend:Urls` (mảng) để đẩy song song lên nhiều backend; máy trạm ngoài xưởng
    /// chỉ có CS-SERVER nên vẫn để `Backend:Url` (chuỗi đơn) như cũ — KHÔNG thêm địa chỉ không
    /// tồn tại vào danh sách, mỗi địa chỉ chết là một lần chờ hết timeout + một dòng cảnh báo
    /// mỗi nhịp đẩy.
    ///
    /// `Backend:Url` vẫn được đọc làm dự phòng để cấu hình trên máy đã cài không bị bỏ qua im
    /// lặng sau khi cập nhật Agent.
    /// </summary>
    public static string[] ResolveBackendUrls(IConfiguration config)
    {
        string[] urls = config.GetSection("Backend:Urls").Get<string[]>() ?? Array.Empty<string>();

        urls = urls.Where(u => !string.IsNullOrWhiteSpace(u))
                   .Select(u => u.Trim().TrimEnd('/'))
                   .Distinct(StringComparer.OrdinalIgnoreCase)
                   .ToArray();

        if (urls.Length > 0)
        {
            return urls;
        }

        string single = config.GetValue<string>("Backend:Url", "http://localhost:8500/api")
                        ?? "http://localhost:8500/api";

        return new[] { single.Trim().TrimEnd('/') };
    }

    /// <summary>
    /// Loại cân mà bản cài này phục vụ: "SMALL" (cân nhỏ, dưới 6kg) hay "LARGE" (cân to).
    ///
    /// Đây là thứ DUY NHẤT phân biệt hai bộ cài — cùng một mã nguồn, khác đúng file
    /// appsettings.json đóng gói kèm (xem agent/installer/appsettings.small.json và
    /// appsettings.large.json). Hai bộ cài chạy song song trên cùng một máy được: khác tên
    /// service, khác thư mục cài, khác UpgradeCode, và khác mã trạm nhờ tiền tố dưới đây.
    ///
    /// Mặc định SMALL để cấu hình cũ (chưa có khóa này) giữ nguyên hành vi: máy đã cài bản
    /// Agent cũ vẫn ra đúng mã trạm "WS-SCALE-&lt;TÊN MÁY&gt;" như trước, không đổi sau khi cập nhật.
    /// </summary>
    public static string ResolveScaleKind(IConfiguration config)
    {
        string kind = (config.GetValue<string>("Workstation:ScaleKind") ?? "").Trim().ToUpperInvariant();

        return kind == "LARGE" ? "LARGE" : "SMALL";
    }

    /// <summary>
    /// Mã trạm của MÁY NÀY. Để trống Workstation:Id trong cấu hình thì tự sinh từ TÊN MÁY —
    /// nhờ đó một bộ cài MSI duy nhất cài lên bao nhiêu máy cũng ra mã khác nhau, không phải
    /// sửa tay từng máy sau khi cài.
    ///
    /// Trước 2026-08-01 bộ cài đóng cứng cùng một mã cho mọi máy, nên hai trạm cân chạy cùng
    /// lúc ghi đè số cân của nhau. Điền Workstation:Id tường minh vẫn được ưu tiên — máy đã
    /// cài từ trước giữ nguyên hành vi cũ, không bị đổi mã sau khi cập nhật Agent.
    ///
    /// TIỀN TỐ theo loại cân (2026-08-03): cân nhỏ giữ nguyên "WS-SCALE-", cân to dùng
    /// "WS-LARGE-". Nhờ vậy cài CẢ HAI bộ lên cùng một máy vẫn ra hai mã trạm khác nhau, hai
    /// bản ghi trạm khác nhau, hai khóa cache số cân khác nhau — không có đường nào để số cân
    /// của cân to lọt sang màn hình cân nhỏ. Cố ý KHÔNG đổi tiền tố của cân nhỏ: đổi là mọi
    /// máy pilot đang chạy tự sinh ra một trạm mới, bỏ lại trạm cũ thành rác trong DB.
    ///
    /// Tên máy Windows chỉ gồm chữ/số/dấu '-' nên gần như luôn qua bộ lọc nguyên vẹn; bộ lọc
    /// chỉ để phòng tên máy lạ (ký tự Unicode) làm hỏng mã trạm dùng làm khóa cache/URL.
    /// </summary>
    public static string ResolveWorkstationId(IConfiguration config)
    {
        string? configured = config.GetValue<string>("Workstation:Id");
        if (!string.IsNullOrWhiteSpace(configured))
        {
            return configured.Trim();
        }

        string prefix = ResolveScaleKind(config) == "LARGE" ? "WS-LARGE-" : "WS-SCALE-";

        var sb = new System.Text.StringBuilder();
        foreach (char c in Environment.MachineName.ToUpperInvariant())
        {
            sb.Append(char.IsLetterOrDigit(c) ? c : '-');
        }

        string machine = sb.ToString().Trim('-');

        return machine.Length == 0 ? prefix + "UNKNOWN" : prefix + machine;
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation(
            "DF Local Agent started for Workstation: {WS} (loại cân {Kind}, đọc cân mỗi {Read}ms, đẩy lên backend mỗi {Push}ms, backend: {Urls})",
            _workstationId, _scaleKind, _readIntervalMs, _pushIntervalMs, string.Join(" + ", _backendUrls));

        double lastLoggedWeight = 0.0;
        DateTime nextPushAt = DateTime.MinValue;
        DateTime nextPrintPollAt = DateTime.MinValue;

        // Việc mạng chạy RỜI khỏi vòng đọc. Nếu await thẳng trong vòng lặp, một lần backend
        // không phản hồi sẽ giữ vòng lặp lại đúng bằng thời gian chờ (HttpClient timeout 5 giây)
        // — tức Agent NGỪNG ĐỌC CÂN 5 giây, làm nhịp 10ms thành vô nghĩa đúng lúc cần nhất.
        // Giữ tối đa 1 việc mỗi loại đang bay: nếu lần trước chưa xong thì bỏ qua lượt này thay
        // vì xếp hàng chồng chất (số cân cũ không đáng để gửi bù, luôn có số mới hơn ngay sau).
        Task? pushInFlight = null;
        Task? printPollInFlight = null;
        Task? rackPollInFlight = null;
        DateTime nextRackPollAt = DateTime.MinValue;

        static bool Idle(Task? t) => t is null || t.IsCompleted;

        // Vòng lặp chạy ở NHỊP ĐỌC (10ms) — port đúng ModRead_putty_log.StartFastLoop của VBA.
        // Mọi việc đắt tiền (HTTP) đều tự cổng theo mốc thời gian riêng bên dưới, nên nhịp lặp
        // nhanh KHÔNG kéo theo tải mạng.
        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                if (_scaleEnabled)
                {
                    // Nạp MỌI lần đọc vào StableFilter, kể cả khi giá trị không đổi — chính việc
                    // đọc lặp lại là thứ làm nên định nghĩa "ổn định" của VBA (2 lần liên tiếp
                    // giống nhau). VBA cũng làm vậy: điều kiện `If s <> rawline` trong
                    // StartFastLoop so chuỗi THÔ với `rawline` vốn đã bị gán giá trị ĐÃ LỌC, nên
                    // gần như luôn đúng và thực tế vòng lặp đẩy mỗi 10ms.
                    var (currentWeight, isStable) = _scaleReader.ReadCurrentWeightWithStability();

                    // TV6 (p0-c-scale-algorithm.md Mục A.10): currentWeight=null nghĩa là lỗi đọc/dữ
                    // liệu rác — KHÔNG push lên backend để tránh ghi đè cache đang giữ giá trị hợp lệ
                    // gần nhất bằng "0.0 giả". Backend cache có TTL 15s tự đóng vai trò "giữ nguyên
                    // giá trị hiển thị cũ" tương đương VBA (ReadLastLineFast/PushRawToForm im lặng bỏ
                    // qua khi không có số hợp lệ), không cần Agent tự làm việc này.
                    if (currentWeight.HasValue)
                    {
                        if (Math.Abs(currentWeight.Value - lastLoggedWeight) > 0.05)
                        {
                            _logger.LogInformation("Scale Weight Changed: {W} kg (Stable: {Stable})", currentWeight.Value, isStable);
                            lastLoggedWeight = currentWeight.Value;
                        }

                        if (DateTime.UtcNow >= nextPushAt && Idle(pushInFlight))
                        {
                            // Không await — xem ghi chú Task? pushInFlight ở trên.
                            pushInFlight = PushWeightToBackendAsync(currentWeight.Value, isStable);
                            nextPushAt = DateTime.UtcNow.AddMilliseconds(_pushIntervalMs);
                        }
                    }
                }

                if (_printEnabled && DateTime.UtcNow >= nextPrintPollAt && Idle(printPollInFlight))
                {
                    printPollInFlight = ProcessPrintWorkAsync();
                    nextPrintPollAt = DateTime.UtcNow.AddMilliseconds(_printPollIntervalMs);
                }

                // SEND OVER 6 — chỉ chạy khi máy trạm được BẬT TƯỜNG MINH (Rack:Enabled).
                // Mặc định tắt vì việc này chiếm chuột thật của máy: bật nhầm ở một trạm không
                // có ứng dụng pha màu sẽ khiến con trỏ nhảy lung tung giữa lúc thợ đang thao tác.
                if (_rackOptions.Enabled && DateTime.UtcNow >= nextRackPollAt && Idle(rackPollInFlight))
                {
                    rackPollInFlight = ProcessRackCommandsAsync();
                    nextRackPollAt = DateTime.UtcNow.AddMilliseconds(_rackOptions.PollIntervalMs);
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Loop execution warning: {Msg}", ex.Message);
            }

            await Task.Delay(_readIntervalMs, stoppingToken);
        }
    }

    /// <summary>
    /// Gộp 2 việc mạng của máy in vào 1 task để vòng đọc cân chỉ phải theo dõi đúng 1 handle.
    /// Bản thân 2 hàm bên trong đã tự bắt hết ngoại lệ nên task này không bao giờ faulted —
    /// quan trọng, vì nó chạy rời khỏi vòng lặp, không có ai await để nhận lỗi.
    /// </summary>
    private async Task ProcessPrintWorkAsync()
    {
        await ProcessPendingPrintJobsAsync();

        if (DateTime.UtcNow >= _nextPrinterReportAt)
        {
            await ReportInstalledPrintersAsync();
            _nextPrinterReportAt = DateTime.UtcNow.Add(PrinterReportInterval);
        }
    }

    /// <summary>
    /// Đẩy số cân lên MỌI backend trong danh sách, SONG SONG.
    ///
    /// Song song chứ không tuần tự: một backend chết sẽ giữ cả lượt đẩy đúng bằng thời gian
    /// chờ timeout (5 giây), làm số cân trên backend còn sống trễ theo — trong khi cả hai vốn
    /// độc lập nhau hoàn toàn.
    ///
    /// Chỉ ghi vào hàng đợi offline khi KHÔNG backend nào nhận được. Còn một nơi nhận là số
    /// cân đã có chỗ lưu, xếp hàng thêm chỉ tạo bản ghi trùng lúc đồng bộ lại.
    /// </summary>
    private async Task PushWeightToBackendAsync(double weight, bool isStable)
    {
        bool[] ketQua = await Task.WhenAll(_backendUrls.Select(url => PushWeightToOneAsync(url, weight, isStable)));

        if (!ketQua.Any(ok => ok))
        {
            _offlineQueue.SaveScaleReading(_workstationId, $"Simulated raw {weight} kg", weight);
        }
    }

    /// <summary>true nếu backend này đã nhận được số cân.</summary>
    private async Task<bool> PushWeightToOneAsync(string backendUrl, double weight, bool isStable)
    {
        try
        {
            var payload = new
            {
                workstation_id = _workstationId,
                weight = weight,
                is_stable = isStable,
                timestamp = DateTime.UtcNow,
                // Gửi kèm để backend TỰ ĐĂNG KÝ đúng loại trạm cho mã trạm sinh từ tên máy
                // (AgentAuth): thiếu role thì trạm mới rơi về type AUTO_REGISTERED và
                // ScannerController::handleOrderScan chặn 403 "chỉ được quét tại các Trạm Cân".
                // machine_name chỉ để hiển thị cho người quản trị nhận ra máy nào là máy nào.
                role = _role,
                // Loại cân của CHÍNH bản cài này. Backend dùng nó cho hai việc:
                //   · tách khóa cache theo IP máy — hai Agent cùng chạy trên một máy (một cân
                //     nhỏ, một cân to) không được ghi đè số của nhau;
                //   · tự đăng ký trạm mới với đúng capability (SMALL_SCALE / LARGE_SCALE) và
                //     đúng màn hình mặc định.
                scale_kind = _scaleKind,
                machine_name = Environment.MachineName
            };

            HttpResponseMessage response = await _httpClient.PostAsJsonAsync($"{backendUrl}/devices/readings", payload);
            if (response.IsSuccessStatusCode)
            {
                _logger.LogDebug("Weight reading pushed to {Url}: {W} kg (Stable: {Stable})", backendUrl, weight, isStable);
                GhiNhanTrangThaiBackend(backendUrl, true, null);
                return true;
            }

            GhiNhanTrangThaiBackend(backendUrl, false, $"tra ve {(int)response.StatusCode}");
            return false;
        }
        catch (Exception ex)
        {
            GhiNhanTrangThaiBackend(backendUrl, false, ex.Message);
            return false;
        }
    }

    /// <summary>
    /// Ghi log CHỈ khi tình trạng của một backend thay đổi — xem ghi chú ở <see cref="_backendOk"/>.
    /// </summary>
    private void GhiNhanTrangThaiBackend(string backendUrl, bool ok, string? lyDo)
    {
        if (_backendOk.TryGetValue(backendUrl, out bool truoc) && truoc == ok)
        {
            return;
        }

        _backendOk[backendUrl] = ok;

        if (ok)
        {
            _logger.LogInformation("Backend {Url}: đã nhận lại được số cân.", backendUrl);
        }
        else
        {
            _logger.LogWarning("Backend {Url} KHÔNG nhận được số cân: {Msg}", backendUrl, lyDo);
        }
    }

    /// <summary>
    /// SEND OVER 6 — lấy lệnh gửi rack rồi mô phỏng chuột vào ứng dụng pha màu.
    ///
    /// Xử lý TUẦN TỰ từng lệnh, không song song: cả máy chỉ có MỘT con chuột, hai lệnh chạy
    /// chồng nhau sẽ dán lẫn mã của nhau vào ô sai.
    /// </summary>
    private async Task ProcessRackCommandsAsync()
    {
        try
        {
            HttpResponseMessage response = await _httpClient.GetAsync($"{_backendUrl}/agents/{_workstationId}/rack-commands");
            if (!response.IsSuccessStatusCode) return;

            var commands = await response.Content.ReadFromJsonAsync<RackCommandDto[]>();
            if (commands == null || commands.Length == 0) return;

            foreach (var cmd in commands)
            {
                _logger.LogInformation("Rack command {Id}: {Action}", cmd.Id, cmd.Action);

                bool ok;
                string? error = null;
                try
                {
                    ok = cmd.Action == "IN"
                        ? _rackSender.SendIn()
                        : _rackSender.SendOut(cmd.Racks ?? Array.Empty<string>());
                    if (!ok) error = "Agent không thực hiện được thao tác (xem log Agent).";
                }
                catch (Exception ex)
                {
                    ok = false;
                    error = ex.Message;
                }

                await AcknowledgeRackCommandAsync(cmd.Id, ok, error);
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to poll rack commands: {Msg}", ex.Message);
        }
    }

    /// <summary>
    /// Báo lại CẢ HAI chiều — lệnh hỏng phải để lại dấu FAILED kèm lý do, nếu không nó nằm
    /// mãi ở SENT và không ai biết hệ pha màu đã nhận hay chưa.
    /// </summary>
    private async Task AcknowledgeRackCommandAsync(string id, bool success, string? error)
    {
        try
        {
            await _httpClient.PostAsJsonAsync(
                $"{_backendUrl}/agents/{_workstationId}/rack-commands/{id}/ack",
                new { success, error_message = error });
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to ack rack command {Id}: {Msg}", id, ex.Message);
        }
    }

    private async Task ProcessPendingPrintJobsAsync()
    {
        try
        {
            HttpResponseMessage response = await _httpClient.GetAsync($"{_backendUrl}/agents/{_workstationId}/jobs");
            if (response.IsSuccessStatusCode)
            {
                var jobs = await response.Content.ReadFromJsonAsync<PrintJobDto[]>();
                if (jobs != null && jobs.Length > 0)
                {
                    foreach (var job in jobs)
                    {
                        _logger.LogInformation("Processing print job: {JobId}", job.Id);
                        
                        string connType = job.PrinterConnectionType ?? "USB";
                        string address = job.PrinterAddress ?? "TSC TE200";

                        bool success = _labelPrinter.PrintLabel(job.LabelPayload, connType, address);
                        // Ack cả 2 chiều — trước đây chỉ ack khi in thành công, khiến lệnh in
                        // lỗi (kẹt giấy, máy in tắt...) đứng mãi ở PENDING, được lấy lại và thử
                        // in lại vô hạn ở vòng lặp sau mà không có bất kỳ dấu vết PRINT_FAILED
                        // nào ghi lại — "C. Lịch sử in thực tế" không bao giờ thấy lỗi thật.
                        await AcknowledgePrintJobAsync(job.Id, success);
                    }
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to poll print jobs: {Msg}", ex.Message);
        }
    }

    private async Task ReportInstalledPrintersAsync()
    {
        try
        {
            var (printers, defaultPrinter) = _printerDiscovery.ListInstalledPrinters();
            if (printers.Count == 0)
            {
                return;
            }

            var payload = new
            {
                workstation_id = _workstationId,
                printers = printers,
                default_printer = defaultPrinter,
            };

            HttpResponseMessage response = await _httpClient.PostAsJsonAsync($"{_backendUrl}/agents/{_workstationId}/printers", payload);
            if (response.IsSuccessStatusCode)
            {
                _logger.LogInformation("Reported {Count} installed printers (default: {Default})", printers.Count, defaultPrinter ?? "none");
            }
            else
            {
                _logger.LogWarning("Backend rejected printer report. Status: {Code}", response.StatusCode);
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Could not report installed printers: {Msg}", ex.Message);
        }
    }

    private async Task AcknowledgePrintJobAsync(string jobId, bool success)
    {
        try
        {
            // Quy ước trạng thái dùng chung toàn hệ thống là PENDING/PRINTED/FAILED (badge
            // trạng thái frontend, print_attempts.status) — trước đây gửi "SUCCESS" không
            // khớp quy ước này.
            var payload = new { job_id = jobId, status = success ? "PRINTED" : "FAILED" };
            await _httpClient.PostAsJsonAsync($"{_backendUrl}/jobs/{jobId}/ack", payload);
            _logger.LogInformation("Acknowledged print job: {JobId} ({Status})", jobId, payload.status);
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to ack print job {JobId}: {Msg}", jobId, ex.Message);
        }
    }

    public class PrintJobDto
    {
        public string Id { get; set; } = string.Empty;
        public string LabelPayload { get; set; } = string.Empty;
        public string? PrinterConnectionType { get; set; }
        public string? PrinterAddress { get; set; }
    }

    /// <summary>Một lệnh SEND OVER 6 lấy từ /agents/{ws}/rack-commands.</summary>
    public class RackCommandDto
    {
        public string Id { get; set; } = string.Empty;
        public string Action { get; set; } = "OUT";
        public string[]? Racks { get; set; }
    }
}
