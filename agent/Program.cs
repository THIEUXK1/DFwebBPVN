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
            .ConfigureServices((hostContext, services) =>
            {
                services.AddSingleton<OfflineQueue>();
                services.AddSingleton<ScaleReader>();
                services.AddSingleton<LabelPrinter>();
                services.AddSingleton<PrinterDiscovery>();
                services.AddHostedService<Worker>();
            });
}
