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
            .UseWindowsService(options => { options.ServiceName = "DFAgent"; })
            .ConfigureServices((hostContext, services) =>
            {
                services.AddSingleton<OfflineQueue>();
                services.AddSingleton<ScaleReader>();
                services.AddSingleton<LabelPrinter>();
                services.AddSingleton<PrinterDiscovery>();
                services.AddHostedService<Worker>();
            });
}
