using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Runtime.InteropServices;
using Microsoft.Extensions.Logging;

namespace DFAgent;

/// <summary>
/// Liệt kê máy in đã cài trên chính máy tính đang chạy Agent (Windows Print Spooler)
/// + máy in mặc định hệ thống — để người vận hành CHỌN từ danh sách thật trên web
/// thay vì gõ tay tên máy in (dễ gõ sai, khiến RawPrinterHelper.OpenPrinter trong
/// LabelPrinter.cs thất bại âm thầm vì tên không khớp chính xác).
///
/// Dùng PowerShell (Get-Printer / Win32_Printer) thay vì thêm gói System.Drawing.Common
/// — tránh phụ thuộc mới, nhất quán với cách LabelPrinter.cs đã gọi thẳng winspool.Drv.
/// </summary>
public class PrinterDiscovery
{
    private readonly ILogger<PrinterDiscovery> _logger;

    public PrinterDiscovery(ILogger<PrinterDiscovery> logger)
    {
        _logger = logger;
    }

    public (List<string> Printers, string? DefaultPrinter) ListInstalledPrinters()
    {
        var printers = new List<string>();
        string? defaultPrinter = null;

        if (!RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
        {
            _logger.LogDebug("Not running on Windows — skipping printer discovery.");
            return (printers, defaultPrinter);
        }

        try
        {
            printers = RunPowerShell("Get-Printer | Select-Object -ExpandProperty Name");
            var defaultResult = RunPowerShell(
                "(Get-CimInstance -ClassName Win32_Printer -Filter \"Default=true\").Name");
            defaultPrinter = defaultResult.FirstOrDefault();
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to enumerate installed printers via PowerShell.");
        }

        return (printers, defaultPrinter);
    }

    private List<string> RunPowerShell(string command)
    {
        var psi = new ProcessStartInfo
        {
            FileName = "powershell.exe",
            Arguments = $"-NoProfile -NonInteractive -ExecutionPolicy Bypass -Command \"{command}\"",
            RedirectStandardOutput = true,
            RedirectStandardError = true,
            UseShellExecute = false,
            CreateNoWindow = true,
        };

        using var process = Process.Start(psi);
        if (process == null)
        {
            return new List<string>();
        }

        string output = process.StandardOutput.ReadToEnd();
        process.WaitForExit(5000);

        return output
            .Split('\n', StringSplitOptions.RemoveEmptyEntries)
            .Select(line => line.Trim())
            .Where(line => line.Length > 0)
            .ToList();
    }
}
