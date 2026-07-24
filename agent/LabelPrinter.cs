using System;
using System.IO;
using System.Net.Sockets;
using System.Runtime.InteropServices;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class LabelPrinter
{
    private readonly ILogger<LabelPrinter> _logger;
    private readonly IConfiguration _config;

    public LabelPrinter(ILogger<LabelPrinter> logger, IConfiguration config)
    {
        _logger = logger;
        _config = config;
    }

    public bool PrintLabel(string tsplCommands, string connectionType, string printerAddress)
    {
        try
        {
            if (connectionType.Equals("LAN", StringComparison.OrdinalIgnoreCase))
            {
                return PrintViaNetwork(tsplCommands, printerAddress);
            }
            else
            {
                return PrintViaUsb(tsplCommands, printerAddress); // printerAddress is the local printer name (e.g. "TSC TE200")
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to execute print job.");
            return false;
        }
    }

    private bool PrintViaNetwork(string tsplCommands, string ipAddress)
    {
        try
        {
            // Port 9100 is standard RAW port for network printers (TSC, Zebra, etc.)
            string[] parts = ipAddress.Split(':');
            string host = parts[0];
            int port = parts.Length > 1 ? int.Parse(parts[1]) : 9100;

            _logger.LogInformation("Sending print job via LAN to {Host}:{Port}", host, port);

            using var client = new TcpClient(host, port);
            using var stream = client.GetStream();
            using var writer = new StreamWriter(stream);
            writer.Write(tsplCommands);
            writer.Flush();

            _logger.LogInformation("Network print job sent successfully.");
            return true;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Network print failed to {Addr}", ipAddress);
            return false;
        }
    }

    private bool PrintViaUsb(string tsplCommands, string printerName)
    {
        try
        {
            _logger.LogInformation("Sending print job via USB Spooler to printer: '{Name}'", printerName);

            // In simulation/dev mode, or if running on non-Windows, we write to a local log file instead of calling Win32 spooler
            if (!RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
            {
                _logger.LogWarning("Not running on Windows. Simulating printer spooling in console.");
                _logger.LogInformation("Raw Printer Data:\n{Data}", tsplCommands);
                return true;
            }

            return RawPrinterHelper.SendStringToPrinter(printerName, tsplCommands);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "USB spooler print failed to '{Name}'", printerName);
            return false;
        }
    }
}

// Win32 raw printing wrapper
internal static class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)]
        public string? pDocName;
        [MarshalAs(UnmanagedType.LPStr)]
        public string? pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)]
        public string? pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool OpenPrinter([MarshalAs(UnmanagedType.LPStr)] string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static bool SendBytesToPrinter(string szPrinterName, IntPtr pBytes, int dwCount)
    {
        IntPtr hPrinter = IntPtr.Zero;
        DOCINFOA di = new DOCINFOA();
        bool bSuccess = false;

        di.pDocName = "DF Raw TSPL Job";
        di.pDataType = "RAW";

        if (OpenPrinter(szPrinterName.Normalize(), out hPrinter, IntPtr.Zero))
        {
            if (StartDocPrinter(hPrinter, 1, di))
            {
                if (StartPagePrinter(hPrinter))
                {
                    bSuccess = WritePrinter(hPrinter, pBytes, dwCount, out int dwWritten);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        return bSuccess;
    }

    public static bool SendStringToPrinter(string szPrinterName, string szString)
    {
        int dwCount = szString.Length;
        IntPtr pBytes = Marshal.StringToCoTaskMemAnsi(szString);
        bool bSuccess = SendBytesToPrinter(szPrinterName, pBytes, dwCount);
        Marshal.FreeCoTaskMem(pBytes);
        return bSuccess;
    }
}
