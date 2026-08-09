using System;
using System.Threading;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Hosting.WindowsServices;

namespace DFAgent;

public class Program
{
    /// <summary>
    /// Giữ suốt đời tiến trình — thả ra sớm là chỗ chặn chạy trùng bên dưới mất tác dụng.
    /// </summary>
    private static Mutex? _khoaMotBan;

    public static void Main(string[] args)
    {
        if (!ChiMotBanChay()) return;

        CreateHostBuilder(args).Build().Run();
    }

    /// <summary>
    /// CHỈ áp cho bản chạy trong phiên người dùng (Tray:Enabled — bản Can to IN/OUT).
    ///
    /// Từ khi Agent nằm ở khay hệ thống thay vì thanh tác vụ, thợ không còn thấy nó trên thanh
    /// tác vụ nữa nên rất dễ bấm lại shortcut Start Menu vì tưởng chưa chạy. Hai bản cùng chạy là
    /// hai vòng lấy lệnh rack tranh nhau một hàng đợi: cùng một lệnh IN/OUT có thể bị bắn hai lần
    /// sang hệ pha màu, hoặc bản này ack trong khi bản kia đang bắn dở.
    ///
    /// Dùng không gian tên "Local\" (theo phiên đăng nhập) chứ không phải "Global\": tài khoản
    /// thường không có quyền SeCreateGlobalPrivilege nên tạo tên Global sẽ bị từ chối, mà bản này
    /// chạy bằng chính tài khoản của thợ.
    ///
    /// Windows Service KHÔNG đi qua đây: Service Control Manager vốn đã không cho chạy hai bản
    /// cùng tên, và service không có ai để hiện hộp thoại.
    /// </summary>
    private static bool ChiMotBanChay()
    {
        if (WindowsServiceHelpers.IsWindowsService()) return true;

        bool khay;
        try { khay = CauHinhSom().GetValue("Tray:Enabled", false); }
        catch { return true; }   // cấu hình hỏng thì để host báo lỗi tử tế, đừng chặn ở đây
        if (!khay) return true;

        string ten = ResolveServiceName();
        _khoaMotBan = new Mutex(initiallyOwned: true, name: $"Local\\DFAgent-{ten}", out bool laBanDauTien);
        if (laBanDauTien) return true;

        TrayIcon.NhacNguoiDung(
            "DF Local Agent đã chạy sẵn trên máy này rồi.\n\n" +
            "Xem biểu tượng ở khay hệ thống, góc phải dưới màn hình (có thể phải bấm mũi tên ˄ để hiện các biểu tượng ẩn).",
            $"DF Local Agent ({ten})");
        return false;
    }

    public static IHostBuilder CreateHostBuilder(string[] args) =>
        Host.CreateDefaultBuilder(args)
            // Cho phép chạy như Windows Service thật (SCM start/stop, working directory
            // đúng thư mục cài đặt) khi cài qua MSI — vẫn chạy console bình thường như cũ
            // khi launch trực tiếp (no-op nếu không phải chạy dưới Service Control Manager).
            .UseWindowsService(options => { options.ServiceName = ResolveServiceName(); })
            .ConfigureServices((hostContext, services) =>
            {
                services.AddSingleton<OfflineQueue>();
                services.AddSingleton<ScaleReader>();
                services.AddSingleton<LabelPrinter>();
                services.AddSingleton<PrinterDiscovery>();
                // SEND OVER 6 — toạ độ chuột đọc từ appsettings mục "Rack" (coding-standards
                // mục 3: không hard-code cấu hình thiết bị trong code). Mặc định là đúng bộ
                // toạ độ của VBA gốc, nhưng Enabled=false để không trạm nào tự nhiên chiếm chuột.
                services.AddSingleton(sp =>
                {
                    var opts = new RackOptions();
                    hostContext.Configuration.GetSection("Rack").Bind(opts);
                    if (opts.SlotPoints.Count == 0)
                    {
                        opts.SlotPoints = new List<RackPoint>
                        {
                            new() { X = 345, Y = 200 },
                            new() { X = 345, Y = 250 },
                            new() { X = 345, Y = 300 },
                            new() { X = 345, Y = 345 },
                            new() { X = 345, Y = 390 },
                            new() { X = 345, Y = 440 },
                        };
                    }
                    return opts;
                });
                services.AddSingleton<RackSender>();
                // Khay hệ thống — chỉ bản chạy trong phiên người dùng (Can to IN/OUT) bật, xem
                // TrayIcon. Mặc định tắt nên hai bản chạy service không đổi hành vi.
                services.AddSingleton(sp =>
                {
                    var opts = new TrayOptions();
                    hostContext.Configuration.GetSection("Tray").Bind(opts);
                    return opts;
                });
                services.AddHostedService<TrayIcon>();
                // Bản chụp số cân dùng chung giữa vòng đọc và đường cục bộ (ADR-013). Singleton
                // là bắt buộc: hai hosted service dưới đây phải nhìn vào ĐÚNG một ô nhớ.
                services.AddSingleton<ScaleSnapshot>();
                services.AddHostedService<Worker>();
                // Đường nhanh cho trình duyệt trên chính máy trạm. Chạy song song với Worker và
                // không phụ thuộc nó: cân chết thì endpoint vẫn trả has_reading=false, để màn
                // hình phân biệt được "cân im" với "không gọi được Agent".
                services.AddHostedService<LocalWeightServer>();
            });

    /// <summary>
    /// Tên service Windows của CHÍNH bản cài này. Phải khớp với ServiceInstall/@Name trong
    /// DFAgentSetup.wxs, nếu không thì tên hiển thị trong log và tên SCM lệch nhau.
    ///
    /// Đọc từ cấu hình vì từ 2026-08-03 có HAI bộ cài độc lập chạy song song được trên cùng
    /// một máy: DFAgentSmall (cân nhỏ) và DFAgentLarge (cân to). Mặc định "DFAgent" cho cấu
    /// hình cũ chưa có khóa này.
    ///
    /// Phải tự dựng cấu hình ở đây (thay vì dùng hostContext) vì UseWindowsService cần tên
    /// service TRƯỚC khi host được build.
    /// </summary>
    private static string ResolveServiceName()
    {
        try
        {
            string? name = CauHinhSom().GetValue<string>("Service:Name");
            return string.IsNullOrWhiteSpace(name) ? "DFAgent" : name.Trim();
        }
        catch
        {
            // Cấu hình hỏng KHÔNG được làm service không khởi động nổi — tên mặc định vẫn
            // chạy được, chỉ hiển thị sai tên trong log.
            return "DFAgent";
        }
    }

    private static IConfigurationRoot? _cauHinhSom;

    /// <summary>
    /// appsettings.json đọc TRƯỚC khi dựng host — cần cho tên service (UseWindowsService phải
    /// biết tên trước khi build) và cho việc chặn chạy trùng bản. Đọc một lần rồi giữ lại.
    /// </summary>
    private static IConfigurationRoot CauHinhSom()
        => _cauHinhSom ??= new ConfigurationBuilder()
            .SetBasePath(AppContext.BaseDirectory)
            .AddJsonFile("appsettings.json", optional: true)
            .Build();
}
