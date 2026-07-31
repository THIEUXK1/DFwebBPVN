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
            // Get-Printer một mình CHỈ thấy máy in "máy-wide" (cài cho mọi người dùng) vì
            // Agent chạy dưới Windows Service (tài khoản Local System, xem Program.cs
            // UseWindowsService) — máy in mạng/LAN được người vận hành tự cài qua "Add
            // a printer" bình thường (không tick "cho mọi người dùng") lại lưu dạng kết nối
            // riêng theo profile (HKCU\Printers\Connections), SYSTEM không đọc được HKCU của
            // user khác. Quét thêm HKEY_USERS\<SID>\Printers\Connections của mọi user đã
            // đăng nhập (profile hive đang load) để không bị thiếu máy in kiểu này (lỗi thật
            // 2026-07-30: máy in đã cài trên máy nhưng dropdown thiếu).
            printers = RunPowerShell(
                "$printers = @(Get-Printer | Select-Object -ExpandProperty Name); " +
                "Get-ChildItem 'Registry::HKEY_USERS' -ErrorAction SilentlyContinue | " +
                "Where-Object { $_.PSChildName -match '^S-1-5-21-\\d+-\\d+-\\d+-\\d+$' } | " +
                "ForEach-Object { $p = 'Registry::HKEY_USERS\\' + $_.PSChildName + '\\Printers\\Connections'; " +
                "if (Test-Path $p) { $printers += (Get-ChildItem $p -ErrorAction SilentlyContinue | " +
                "ForEach-Object { $_.PSChildName -replace ',', '\\' }) } }; " +
                "$printers | Sort-Object -Unique");
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
