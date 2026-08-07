using System.Runtime.InteropServices;
using Microsoft.Extensions.Logging;

namespace DFAgent;

/// <summary>
/// Bắn mã rack sang ứng dụng pha màu bằng MÔ PHỎNG CHUỘT + CLIPBOARD.
///
/// Port 1-1 từ mã nguồn thật của workbook "5.Semiauto- lockmove SEND OVER6 -
/// delta-stable-final-221.xlsm" (scaleform.btn_Out_Click / btn_In_Click +
/// Mod_sendRackauto.FireRackBatch + ModAPI_mouse.SendTextToApp/ClickAt +
/// ModDelay_paste.SmartDelay). Mã nguồn lấy được bằng cách giải nén trực tiếp
/// vbaProject.bin theo MS-OVBA — VBA project bị khoá mật khẩu nên không mở được qua Excel,
/// nhưng mật khẩu chỉ khoá giao diện VBE chứ không mã hoá module.
///
/// TRÌNH TỰ GỐC — giữ nguyên cả thứ tự lẫn các mốc trễ, vì đây là thao tác vào ứng dụng
/// ngoài: rút ngắn trễ là dán trượt ô, bỏ bước là app đích chưa kịp nhận focus.
///
///   OUT:  chờ 150 -> click (10,100) -> chờ 220 -> click (365,680) -> chờ 200
///         -> [6 lần: đặt clipboard, click ô, Ctrl+V, chờ 200]
///         -> click (750,215) xác nhận -> chờ 200
///
///   IN:   chờ 150 -> click (10,100) -> chờ 220 -> click (750,430) -> chờ 200
///
/// Hai điểm dễ làm sai (bản đầu của Agent này từng sai cả hai):
///   1. FireRackBatch gửi ĐỦ 6 Ô, KỂ CẢ Ô RỖNG — dán chuỗi rỗng chính là để XOÁ giá trị
///      còn sót của lô trước bên ứng dụng đích. Bỏ qua ô rỗng là để lại rác.
///   2. Nút xác nhận của IN là (750,430), KHÁC nút xác nhận của OUT (750,215).
///
/// KHÔNG port: SetTopMost Me, False/True — đó là thao tác gỡ/bật always-on-top của chính
/// UserForm VBA, Agent không có form nên không có gì để gỡ.
///
/// ===== CẢNH BÁO VẬN HÀNH =====
/// Đây là RPA mù: click theo toạ độ TUYỆT ĐỐI, KHÔNG kiểm tra cửa sổ nào đang ở đó. Nếu
/// ứng dụng pha màu không mở đúng vị trí/độ phân giải như lúc hiệu chỉnh, mã rack sẽ bị dán
/// vào cửa sổ bất kỳ. Toạ độ nằm trong appsettings.json mục "Rack" để hiệu chỉnh mà không
/// phải build lại. Xem ADR-012.
/// </summary>
public class RackSender
{
    private const uint MOUSEEVENTF_LEFTDOWN = 0x0002;
    private const uint MOUSEEVENTF_LEFTUP = 0x0004;
    private const byte VK_CONTROL = 0x11;
    private const byte VK_V = 0x56;
    private const uint KEYEVENTF_KEYUP = 0x0002;

    [DllImport("user32.dll")]
    private static extern bool SetCursorPos(int x, int y);

    [DllImport("user32.dll")]
    private static extern void mouse_event(uint dwFlags, int dx, int dy, uint dwData, int dwExtraInfo);

    [DllImport("user32.dll")]
    private static extern void keybd_event(byte bVk, byte bScan, uint dwFlags, int dwExtraInfo);

    private readonly ILogger<RackSender> _logger;
    private readonly RackOptions _options;

    public RackSender(ILogger<RackSender> logger, RackOptions options)
    {
        _logger = logger;
        _options = options;
    }

    /// <summary>btn_Out_Click — nhánh gửi lô rack (rackBatch1) sang ứng dụng pha màu.</summary>
    public bool SendOut(IReadOnlyList<string> racks)
    {
        if (!GuardWindows()) return false;

        try
        {
            EnterTargetApp();

            // FireRackBatch: batch(1)..batch(6), KHÔNG bỏ ô rỗng.
            for (int i = 0; i < _options.SlotPoints.Count; i++)
            {
                string value = i < racks.Count ? (racks[i] ?? string.Empty).Trim() : string.Empty;
                var point = _options.SlotPoints[i];

                if (!SendTextToApp(value, point.X, point.Y))
                {
                    _logger.LogError("Không đặt được clipboard cho ô {Slot} (giá trị '{Value}')", i + 1, value);
                    return false;
                }
                SmartDelay(_options.StepDelayMs);
            }

            ClickAt(_options.ConfirmPoint.X, _options.ConfirmPoint.Y);
            SmartDelay(_options.StepDelayMs);

            _logger.LogInformation("Đã bắn {Count} ô rack sang hệ pha màu.", _options.SlotPoints.Count);
            return true;
        }
        catch (Exception ex)
        {
            _logger.LogError("Lỗi khi bắn rack: {Msg}", ex.Message);
            return false;
        }
    }

    /// <summary>
    /// btn_In_Click — bấm nút NHẬN bên ứng dụng pha màu. Việc dồn lô 2 lên lô 1 do phía web
    /// giữ trạng thái và tự làm, Agent không nhớ lô nào.
    /// </summary>
    public bool SendIn()
    {
        if (!GuardWindows()) return false;

        try
        {
            SmartDelay(_options.PreDelayMs);
            ClickAt(_options.ActivatePoint.X, _options.ActivatePoint.Y);
            SmartDelay(_options.ActivateDelayMs);

            ClickAt(_options.InPoint.X, _options.InPoint.Y);
            SmartDelay(_options.StepDelayMs);

            _logger.LogInformation("Đã bấm NHẬN (IN) bên hệ pha màu.");
            return true;
        }
        catch (Exception ex)
        {
            _logger.LogError("Lỗi khi bấm IN: {Msg}", ex.Message);
            return false;
        }
    }

    /// <summary>Phần mở đầu dùng chung của cả 2 nhánh btn_Out_Click.</summary>
    private void EnterTargetApp()
    {
        SmartDelay(_options.PreDelayMs);

        // Click vào một điểm trống của ứng dụng đích để nó nhận focus.
        ClickAt(_options.ActivatePoint.X, _options.ActivatePoint.Y);
        SmartDelay(_options.ActivateDelayMs);

        // Click mở đúng vùng nhập rack bên ứng dụng đích.
        ClickAt(_options.PrepPoint.X, _options.PrepPoint.Y);
        SmartDelay(_options.StepDelayMs);
    }

    private bool GuardWindows()
    {
        if (OperatingSystem.IsWindows()) return true;
        _logger.LogError("RackSender chỉ chạy được trên Windows.");
        return false;
    }

    /// <summary>
    /// ModAPI_mouse.SendTextToApp — đặt clipboard, click vào ô, dán.
    ///
    /// Nguyên văn bản gốc (giải nén 07/08/2026 từ vbaProject.bin của workbook cân to):
    ///
    ///     Public Sub SendTextToApp(ByVal s As String, ByVal x As Long, ByVal y As Long)
    ///         SetClipboardText s
    ///         ClickAt x, y
    ///         DoEvents
    ///         SendKeys "^v"
    ///         DoEvents
    ///     End Sub
    ///
    /// HAI LỆNH `DoEvents` NÀY TỪNG BỊ BỎ SÓT khi port (sửa 07/08/2026). Chúng không phải thủ tục
    /// thừa của VBA: `DoEvents` nhường quyền cho hệ điều hành bơm hết hàng đợi thông điệp, tức
    /// **chờ ứng dụng đích xử lý xong cú click và đưa con trỏ nhập vào đúng ô** rồi mới dán. Bỏ đi
    /// thì Agent bắn Ctrl+V chỉ vài micro-giây sau cú click, lúc app đích còn chưa kịp đổi tiêu
    /// điểm — mã rack rơi vào ô đang focus trước đó hoặc mất hẳn. Đây đúng là triệu chứng "bấm OUT
    /// mà không thấy nó làm giống bản Excel".
    ///
    /// Agent không có message pump để mà `DoEvents`, nên thay bằng một khoảng nghỉ ngắn — cùng tác
    /// dụng đứng từ phía ứng dụng đích: cho nó thời gian xử lý xong việc trước.
    /// </summary>
    private bool SendTextToApp(string s, int x, int y)
    {
        if (!SetClipboardText(s)) return false;
        ClickAt(x, y);
        SmartDelay(_options.PasteDelayMs);   // DoEvents thứ nhất — chờ app đích nhận cú click
        PressCtrlV();
        SmartDelay(_options.PasteDelayMs);   // DoEvents thứ hai — chờ app đích nuốt xong Ctrl+V
        return true;
    }

    /// <summary>
    /// ModAPI_mouse.ClickAt — đặt con trỏ rồi nhấn/nhả, KHÔNG có độ trễ xen giữa (bản gốc
    /// không có; mọi độ trễ đều nằm ở SmartDelay giữa các bước).
    /// </summary>
    private static void ClickAt(int x, int y)
    {
        SetCursorPos(x, y);
        mouse_event(MOUSEEVENTF_LEFTDOWN, 0, 0, 0, 0);
        mouse_event(MOUSEEVENTF_LEFTUP, 0, 0, 0, 0);
    }

    /// <summary>SendKeys "^v".</summary>
    private static void PressCtrlV()
    {
        keybd_event(VK_CONTROL, 0, 0, 0);
        keybd_event(VK_V, 0, 0, 0);
        keybd_event(VK_V, 0, KEYEVENTF_KEYUP, 0);
        keybd_event(VK_CONTROL, 0, KEYEVENTF_KEYUP, 0);
    }

    /// <summary>
    /// ModDelay_paste.SmartDelay — bản gốc là vòng bận có DoEvents để Excel vẫn bơm message
    /// trong lúc chờ. Agent không có message pump nào cần bơm, nên Thread.Sleep là tương
    /// đương đúng và còn không đốt CPU.
    /// </summary>
    private static void SmartDelay(int ms)
    {
        if (ms > 0) Thread.Sleep(ms);
    }

    /* ---------- Clipboard: Win32 thuần, đúng bộ hàm ModAPI_mouse.SetClipboardText dùng ---------- */

    [DllImport("user32.dll", SetLastError = true)]
    private static extern bool OpenClipboard(IntPtr hWndNewOwner);

    [DllImport("user32.dll", SetLastError = true)]
    private static extern bool CloseClipboard();

    [DllImport("user32.dll", SetLastError = true)]
    private static extern bool EmptyClipboard();

    [DllImport("user32.dll", SetLastError = true)]
    private static extern IntPtr SetClipboardData(uint uFormat, IntPtr hMem);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern IntPtr GlobalAlloc(uint uFlags, UIntPtr dwBytes);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern IntPtr GlobalLock(IntPtr hMem);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern bool GlobalUnlock(IntPtr hMem);

    [DllImport("kernel32.dll", SetLastError = true)]
    private static extern IntPtr GlobalFree(IntPtr hMem);

    private const uint GMEM_MOVEABLE = 0x0002;
    private const uint CF_UNICODETEXT = 13;

    /// <summary>
    /// Cố ý KHÔNG dùng System.Windows.Forms.Clipboard: project nhắm net8.0 chứ không phải
    /// net8.0-windows, kéo WinForms vào sẽ phải đổi TargetFramework và ảnh hưởng cả pipeline
    /// publish MSI. Bản gốc VBA cũng gọi thẳng đúng bộ hàm Win32 này.
    /// </summary>
    private static bool SetClipboardText(string text)
    {
        if (!OpenClipboard(IntPtr.Zero)) return false;

        IntPtr hMem = IntPtr.Zero;
        try
        {
            EmptyClipboard();

            int bytes = (text.Length + 1) * 2; // +1 cho ký tự kết thúc chuỗi
            hMem = GlobalAlloc(GMEM_MOVEABLE, (UIntPtr) bytes);
            if (hMem == IntPtr.Zero) return false;

            IntPtr target = GlobalLock(hMem);
            if (target == IntPtr.Zero) return false;
            try
            {
                if (text.Length > 0) Marshal.Copy(text.ToCharArray(), 0, target, text.Length);
                Marshal.WriteInt16(target, text.Length * 2, 0);
            }
            finally
            {
                GlobalUnlock(hMem);
            }

            if (SetClipboardData(CF_UNICODETEXT, hMem) == IntPtr.Zero) return false;

            // Đặt thành công thì HỆ ĐIỀU HÀNH sở hữu vùng nhớ này — giải phóng ở đây là lỗi
            // dùng-sau-khi-giải-phóng. Bỏ handle để khối finally không đụng vào.
            hMem = IntPtr.Zero;
            return true;
        }
        catch
        {
            return false;
        }
        finally
        {
            if (hMem != IntPtr.Zero) GlobalFree(hMem);
            CloseClipboard();
        }
    }
}

/// <summary>Toạ độ + mốc trễ, đọc từ appsettings.json mục "Rack". Mặc định = đúng bản VBA gốc.</summary>
public class RackOptions
{
    /// <summary>Điểm click để ứng dụng đích nhận focus — VBA: ClickAt 10, 100.</summary>
    public RackPoint ActivatePoint { get; set; } = new() { X = 10, Y = 100 };

    /// <summary>Click mở vùng nhập rack — VBA: ClickAt 365, 680.</summary>
    public RackPoint PrepPoint { get; set; } = new() { X = 365, Y = 680 };

    /// <summary>6 ô nhập rack — VBA: 345 x {200,250,300,345,390,440}.</summary>
    public List<RackPoint> SlotPoints { get; set; } = new();

    /// <summary>Nút xác nhận sau khi gửi rack (OUT) — VBA: ClickAt 750, 215.</summary>
    public RackPoint ConfirmPoint { get; set; } = new() { X = 750, Y = 215 };

    /// <summary>Nút NHẬN (IN) — VBA: ClickAt 750, 430. KHÁC nút xác nhận của OUT.</summary>
    public RackPoint InPoint { get; set; } = new() { X = 750, Y = 430 };

    /// <summary>SmartDelay 150 — sau khi nhường màn hình cho ứng dụng đích.</summary>
    public int PreDelayMs { get; set; } = 150;

    /// <summary>SmartDelay 220 — sau click kích hoạt cửa sổ.</summary>
    public int ActivateDelayMs { get; set; } = 220;

    /// <summary>SmartDelay 200 — giữa các bước còn lại.</summary>
    public int StepDelayMs { get; set; } = 200;

    /// <summary>
    /// Thay cho hai lệnh `DoEvents` trong `SendTextToApp` của VBA — xem ghi chú ở hàm đó.
    ///
    /// 60ms là mức đủ cho ứng dụng đích đổi tiêu điểm trên máy trạm bình thường; tổng thêm vào một
    /// lượt OUT là 6 x 120ms = 0.72 giây, vẫn nằm gọn trong 12 giây mà màn hình chờ Agent ack.
    /// Máy chậm/app đích nặng thì tăng con số này lên trước khi nghĩ tới việc đổi toạ độ.
    /// </summary>
    public int PasteDelayMs { get; set; } = 60;

    public int PollIntervalMs { get; set; } = 2000;
    public bool Enabled { get; set; } = false;
}

public class RackPoint
{
    public int X { get; set; }
    public int Y { get; set; }
}
