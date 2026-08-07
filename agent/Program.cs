using System;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;

namespace DFAgent;

public class Program
{
    public static void Main(string[] args)
    {
        CreateHostBuilder(args).Build().Run();
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
            string? name = new ConfigurationBuilder()
                .SetBasePath(AppContext.BaseDirectory)
                .AddJsonFile("appsettings.json", optional: true)
                .Build()
                .GetValue<string>("Service:Name");

            return string.IsNullOrWhiteSpace(name) ? "DFAgent" : name.Trim();
        }
        catch
        {
            // Cấu hình hỏng KHÔNG được làm service không khởi động nổi — tên mặc định vẫn
            // chạy được, chỉ hiển thị sai tên trong log.
            return "DFAgent";
        }
    }
}
