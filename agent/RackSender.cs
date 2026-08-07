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
/// `SetTopMost Me, False/True` — TỪ 4.5.0.0 ĐÃ PORT (trước đó bỏ qua với lý do "Agent không
/// có form nên không có gì để gỡ"). Cái che ứng dụng pha màu bây giờ là TRÌNH DUYỆT chạy toàn
/// màn hình của màn cân, nên không gỡ nó ra thì click (10,100) trúng trình duyệt và cả 6 lần
/// Ctrl+V đổ vào trang web — mà Agent vẫn báo DONE.
///
/// Cách port (4.6.0.0) giữ đúng tinh thần bản gốc là **không cần biết ứng dụng pha màu tên gì**:
/// nhìn xem cửa sổ nào đang nằm dưới các toạ độ sắp click, thấy trình duyệt thì đẩy nó xuống đáy
/// (= `SetTopMost Me, False`), cái lộ ra chính là ứng dụng đích; bắn xong trả tiêu điểm về trình
/// duyệt (= `SetTopMost Me, True`). Cài xong là chạy, không phải khai báo gì.
/// `Rack:TargetWindowTitle` chỉ là đường dự phòng khi vùng đó có nhiều cửa sổ chồng nhau.
/// Xem <see cref="WindowFocus"/>.
///
/// ===== CẢNH BÁO VẬN HÀNH =====
/// Toạ độ vẫn là toạ độ MÀN HÌNH TUYỆT ĐỐI đúng như bản gốc — chỉ có bước "đúng cửa sổ đích
/// đang ở trước" là được bảo đảm, còn "đúng ô trong cửa sổ đó" thì vẫn phụ thuộc việc ứng
/// dụng pha màu mở đúng vị trí/độ phân giải như lúc hiệu chỉnh. Toạ độ nằm trong
/// appsettings.json mục "Rack" để hiệu chỉnh mà không phải build lại. Xem ADR-012.
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

    /// <summary>
    /// Lý do THẬT của lần bắn hỏng gần nhất, để Worker ack đúng lý do đó lên backend thay vì câu
    /// chung chung "xem log Agent" — thợ đứng ở màn cân không mở được log của máy trạm, mà
    /// "không tìm thấy cửa sổ ứng dụng pha màu" với "clipboard bị ứng dụng khác giữ" là hai việc
    /// phải xử lý khác hẳn nhau.
    /// </summary>
    public string? LoiCuoi { get; private set; }

    public RackSender(ILogger<RackSender> logger, RackOptions options)
    {
        _logger = logger;
        _options = options;
    }

    /// <summary>btn_Out_Click — nhánh gửi lô rack (rackBatch1) sang ứng dụng pha màu.</summary>
    public bool SendOut(IReadOnlyList<string> racks)
    {
        LoiCuoi = null;
        if (!GuardWindows()) return false;

        // `SetTopMost Me, True` ở cuối bản gốc trả màn hình về cho form cân. Nhớ cửa sổ đang
        // đứng trước (trình duyệt của thợ) TRƯỚC KHI cướp tiêu điểm, nếu không thì sau lượt bắn
        // thợ phải tự bấm lại vào cửa sổ web mới quét/gõ tiếp được.
        IntPtr cuaSoTruoc = WindowFocus.CuaSoDangHoatDong();

        try
        {
            if (!DuaUngDungDichLenTruoc()) return false;

            SmartDelay(_options.PreDelayMs);

            // Click vào một điểm trống của ứng dụng đích để nó nhận focus.
            ClickAt(_options.ActivatePoint.X, _options.ActivatePoint.Y);
            SmartDelay(_options.ActivateDelayMs);

            // Click mở đúng vùng nhập rack bên ứng dụng đích.
            ClickAt(_options.PrepPoint.X, _options.PrepPoint.Y);
            SmartDelay(_options.StepDelayMs);

            // FireRackBatch: batch(1)..batch(6), KHÔNG bỏ ô rỗng.
            for (int i = 0; i < _options.SlotPoints.Count; i++)
            {
                string value = i < racks.Count ? (racks[i] ?? string.Empty).Trim() : string.Empty;
                var point = _options.SlotPoints[i];

                if (!SendTextToApp(value, point.X, point.Y))
                {
                    LoiCuoi = $"Không đặt được clipboard cho ô {i + 1} (giá trị '{value}') — "
                              + "có ứng dụng khác đang giữ clipboard.";
                    _logger.LogError("{Msg}", LoiCuoi);
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
            LoiCuoi = "Lỗi khi bắn rack: " + ex.Message;
            _logger.LogError("{Msg}", LoiCuoi);
            return false;
        }
        finally
        {
            TraLaiTieuDiem(cuaSoTruoc);
        }
    }

    /// <summary>
    /// btn_In_Click — bấm nút NHẬN bên ứng dụng pha màu. Việc dồn lô 2 lên lô 1 do phía web
    /// giữ trạng thái và tự làm, Agent không nhớ lô nào.
    /// </summary>
    public bool SendIn()
    {
        LoiCuoi = null;
        if (!GuardWindows()) return false;

        IntPtr cuaSoTruoc = WindowFocus.CuaSoDangHoatDong();

        try
        {
            if (!DuaUngDungDichLenTruoc()) return false;

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
            LoiCuoi = "Lỗi khi bấm IN: " + ex.Message;
            _logger.LogError("{Msg}", LoiCuoi);
            return false;
        }
        finally
        {
            TraLaiTieuDiem(cuaSoTruoc);
        }
    }

    /// <summary>
    /// Tương đương `SetTopMost Me, False` — gỡ thứ đang che ứng dụng pha màu ra khỏi mặt trước.
    ///
    /// KHÔNG tìm thấy cửa sổ đích là FAILED ngay, không bắn mù: click theo toạ độ tuyệt đối lúc
    /// ứng dụng đích không ở trước thì mã rack rơi vào cửa sổ bất kỳ (thường là chính trình duyệt
    /// đang mở màn cân), trong khi mọi lệnh Win32 đều trả về thành công nên Agent sẽ ack DONE.
    /// Thà báo hỏng để thợ dùng nút COPY dán tay còn hơn.
    /// </summary>
    private bool DuaUngDungDichLenTruoc()
    {
        if (!_options.RequireTargetWindow)
        {
            _logger.LogWarning(
                "Rack:RequireTargetWindow=false — đang bắn MÙ theo toạ độ tuyệt đối, không kiểm tra "
                + "cửa sổ nào đang ở đó (hành vi Agent 4.4 trở về trước).");
            return true;
        }

        if (!_options.CoCauHinhCuaSoDich) return TuDoRoiDuaLenTruoc();

        var ungVien = WindowFocus.Tim(_options.TargetWindowTitle, _options.TargetProcessName);

        if (ungVien.Count == 0)
        {
            LoiCuoi = $"Không tìm thấy cửa sổ ứng dụng pha màu (tiêu đề chứa "
                      + $"'{_options.TargetWindowTitle}'{MoTaTienTrinh()}) — ứng dụng chưa mở hoặc "
                      + "cấu hình sai. Agent không bắn mù.";
            _logger.LogError(
                "{Msg}\n   Các cửa sổ đang mở trên máy này:\n{DanhSach}",
                LoiCuoi, WindowFocus.MoTaDanhSach(WindowFocus.LietKe()));
            return false;
        }

        if (ungVien.Count > 1)
        {
            // Không tự đoán: lấy cái đầu để việc vẫn chạy, nhưng ghi rõ ra vì bắn nhầm cửa sổ là
            // dán mã rack vào ứng dụng bất kỳ.
            _logger.LogWarning(
                "Có {Count} cửa sổ cùng khớp — dùng cái đầu tiên. Đặt tiêu đề cụ thể hơn:\n{DanhSach}",
                ungVien.Count, WindowFocus.MoTaDanhSach(ungVien));
        }

        var dich = ungVien[0];

        if (!WindowFocus.DuaLenTruoc(dich.Handle, _options.ForegroundTimeoutMs))
        {
            LoiCuoi = $"Không đưa được cửa sổ {dich} lên trước sau {_options.ForegroundTimeoutMs}ms "
                      + "— có cửa sổ khác đang ghim always-on-top hoặc trình duyệt đang ở chế độ kiosk "
                      + "không nhường. Agent không bắn mù.";
            _logger.LogError("{Msg}", LoiCuoi);
            return false;
        }

        CanhBaoToaDoNamNgoai(dich);
        _logger.LogInformation("Đã đưa cửa sổ đích lên trước: {CuaSo}", dich);
        return true;
    }

    /// <summary>
    /// TỰ DÒ cửa sổ đích — đường mặc định, thợ KHÔNG phải khai báo gì.
    ///
    /// Bản VBA gốc cũng không hề biết ứng dụng pha màu tên gì: `SetTopMost Me, False` chỉ đẩy cái
    /// đang che (form Excel) xuống, rồi click thẳng vào toạ độ — cái gì nằm ở đó thì nhận. Làm lại
    /// đúng như vậy, chỉ thêm một bước hỏi trước khi click:
    ///
    ///   1. Nhìn xem cửa sổ nào đang nằm dưới các toạ độ sắp click.
    ///   2. Nếu đó là TRÌNH DUYỆT (thứ duy nhất thay thế vai trò "form Excel đang che") thì đẩy nó
    ///      xuống đáy — đây chính là `SetTopMost Me, False` — rồi nhìn lại.
    ///   3. Cái lộ ra chính là ứng dụng pha màu. Không có gì lộ ra (chỉ thấy nền desktop) thì báo
    ///      hỏng, vì lúc đó bắn ra cũng chỉ click vào hư không.
    ///
    /// Khai báo `Rack:TargetWindowTitle` chỉ cần tới khi máy trạm có nhiều cửa sổ chồng lên nhau
    /// tại vùng đó và bước tự dò chọn nhầm.
    /// </summary>
    private bool TuDoRoiDuaLenTruoc()
    {
        var diem = TapDiemClick();

        var dich = WindowFocus.CuaSoTaiCacDiem(diem);

        if (dich != null && WindowFocus.LaTrinhDuyet(dich))
        {
            _logger.LogInformation("Trình duyệt đang che vùng click ({CuaSo}) — đẩy xuống đáy.", dich);
            WindowFocus.DayXuongDay(dich.Handle);
            // Cho hệ điều hành vẽ lại thứ tự chồng trước khi hỏi lại — đúng vai trò của
            // `DoEvents` + `SmartDelay 150` ngay sau `SetTopMost Me, False` trong bản gốc.
            SmartDelay(_options.PreDelayMs);
            dich = WindowFocus.CuaSoTaiCacDiem(diem);
        }

        if (dich == null || WindowFocus.LaTrinhDuyet(dich))
        {
            LoiCuoi = dich == null
                ? "Tại vùng toạ độ cần click không có cửa sổ ứng dụng nào (chỉ thấy nền desktop) "
                  + "— MỞ ứng dụng pha màu lên rồi bấm lại. Agent không bắn mù."
                : "Sau khi lùi trình duyệt xuống vẫn chỉ thấy trình duyệt tại vùng click "
                  + "— ứng dụng pha màu chưa mở, hoặc đang mở ở màn hình/vị trí khác. Agent không bắn mù.";
            _logger.LogError(
                "{Msg}\n   Các cửa sổ đang mở trên máy này:\n{DanhSach}",
                LoiCuoi, WindowFocus.MoTaDanhSach(WindowFocus.LietKe()));
            return false;
        }

        if (!WindowFocus.DuaLenTruoc(dich.Handle, _options.ForegroundTimeoutMs))
        {
            LoiCuoi = $"Không đưa được cửa sổ {dich} lên trước sau {_options.ForegroundTimeoutMs}ms.";
            _logger.LogError("{Msg}", LoiCuoi);
            return false;
        }

        _logger.LogInformation("Tự dò được cửa sổ đích và đã đưa lên trước: {CuaSo}", dich);
        return true;
    }

    /// <summary>
    /// Toàn bộ toạ độ mà một lượt bắn có thể chạm tới — dùng để bỏ phiếu xem cửa sổ nào đang nằm
    /// dưới đó. Gộp cả điểm của OUT lẫn IN: bỏ phiếu theo đa số nên một hai điểm lệch không đổi
    /// kết quả, mà lại không phải tách hai danh sách gần giống nhau.
    /// </summary>
    private List<(int X, int Y)> TapDiemClick()
    {
        var diem = new List<(int, int)>
        {
            (_options.ActivatePoint.X, _options.ActivatePoint.Y),
            (_options.PrepPoint.X, _options.PrepPoint.Y),
            (_options.ConfirmPoint.X, _options.ConfirmPoint.Y),
            (_options.InPoint.X, _options.InPoint.Y),
        };
        diem.AddRange(_options.SlotPoints.Select(p => (p.X, p.Y)));
        return diem;
    }

    /// <summary>
    /// Toạ độ trong cấu hình là toạ độ MÀN HÌNH của máy hiệu chỉnh gốc. Cửa sổ đích lên trước rồi
    /// mà điểm click lại nằm ngoài khung của nó thì cú click rơi ra desktop hoặc cửa sổ phía dưới
    /// — vẫn hỏng, chỉ là hỏng theo kiểu khác. Cảnh báo chứ không chặn: có ứng dụng vẽ vùng nhập
    /// ngoài khung cửa sổ chính (menu bung, popup), chặn thì lại chặn nhầm việc đang chạy tốt.
    /// </summary>
    private void CanhBaoToaDoNamNgoai(WindowFocus.CuaSo dich)
    {
        var ngoai = new List<string>();

        if (!dich.ChuaDiem(_options.ActivatePoint.X, _options.ActivatePoint.Y))
            ngoai.Add($"ActivatePoint({_options.ActivatePoint.X},{_options.ActivatePoint.Y})");
        if (!dich.ChuaDiem(_options.PrepPoint.X, _options.PrepPoint.Y))
            ngoai.Add($"PrepPoint({_options.PrepPoint.X},{_options.PrepPoint.Y})");
        for (int i = 0; i < _options.SlotPoints.Count; i++)
        {
            var p = _options.SlotPoints[i];
            if (!dich.ChuaDiem(p.X, p.Y)) ngoai.Add($"SlotPoints[{i + 1}]({p.X},{p.Y})");
        }
        if (!dich.ChuaDiem(_options.ConfirmPoint.X, _options.ConfirmPoint.Y))
            ngoai.Add($"ConfirmPoint({_options.ConfirmPoint.X},{_options.ConfirmPoint.Y})");
        if (!dich.ChuaDiem(_options.InPoint.X, _options.InPoint.Y))
            ngoai.Add($"InPoint({_options.InPoint.X},{_options.InPoint.Y})");

        if (ngoai.Count > 0)
        {
            _logger.LogWarning(
                "Toạ độ nằm NGOÀI khung cửa sổ đích {CuaSo}: {DanhSach}. Đo lại toạ độ trên chính "
                + "máy này (mục Rack trong appsettings.json).",
                dich, string.Join(", ", ngoai));
        }
    }

    /// <summary>Tương đương `SetTopMost Me, True` — trả màn hình về cho trình duyệt của thợ.</summary>
    private void TraLaiTieuDiem(IntPtr cuaSoTruoc)
    {
        if (!_options.RestoreForeground || cuaSoTruoc == IntPtr.Zero) return;

        // Không quan tâm kết quả: trả được thì tốt, không trả được thì thợ bấm một cái vào cửa sổ
        // web. Không được để việc này che mất kết quả THẬT của lượt bắn.
        try { WindowFocus.DuaLenTruoc(cuaSoTruoc, _options.ForegroundTimeoutMs); }
        catch (Exception ex) { _logger.LogWarning("Không trả được tiêu điểm về trình duyệt: {Msg}", ex.Message); }
    }

    private string MoTaTienTrinh()
        => string.IsNullOrWhiteSpace(_options.TargetProcessName)
            ? string.Empty
            : $", tiến trình '{_options.TargetProcessName}'";

    private bool GuardWindows()
    {
        if (OperatingSystem.IsWindows()) return true;
        LoiCuoi = "RackSender chỉ chạy được trên Windows.";
        _logger.LogError("{Msg}", LoiCuoi);
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

    /* ---------- Cửa sổ đích (từ 4.5.0.0) — thay cho SetTopMost Me, False/True ---------- */

    /// <summary>
    /// TUỲ CHỌN — để trống là Agent TỰ DÒ (xem <c>RackSender.TuDoRoiDuaLenTruoc</c>), cài xong dùng
    /// được ngay không phải khai báo gì. Chỉ điền khi máy trạm có nhiều cửa sổ chồng nhau tại vùng
    /// toạ độ và bước tự dò chọn nhầm: MỘT PHẦN tiêu đề cửa sổ, không phân biệt hoa thường.
    /// </summary>
    public string TargetWindowTitle { get; set; } = string.Empty;

    /// <summary>
    /// TUỲ CHỌN, như trên — tên tiến trình (không cần đuôi .exe). Khai báo cùng tiêu đề thì phải
    /// khớp CẢ HAI. Dùng khi tiêu đề đổi theo mẻ đang chạy.
    /// </summary>
    public string TargetProcessName { get; set; } = string.Empty;

    /// <summary>
    /// Không xác định được cửa sổ đích thì BÁO HỎNG thay vì bắn mù. Mặc định bật.
    ///
    /// Đặt false là quay lại đúng hành vi 3.2.0.0 → 4.4.0.0: click theo toạ độ tuyệt đối bất kể
    /// cửa sổ nào đang ở đó, kể cả khi thứ nằm ở đó là trình duyệt của chính màn cân. Chỉ dùng khi
    /// việc tự dò gây phiền hơn là giúp, và phải chấp nhận rủi ro dán mã rack vào nhầm cửa sổ mà
    /// vẫn được báo là thành công.
    /// </summary>
    public bool RequireTargetWindow { get; set; } = true;

    /// <summary>
    /// Chờ tối đa bao lâu cho cửa sổ đích thật sự lên trước. 1.5 giây là dư cho một cú
    /// SetForegroundWindow trên máy trạm bình thường, mà vẫn nằm gọn trong 12 giây màn hình chờ
    /// Agent ack.
    /// </summary>
    public int ForegroundTimeoutMs { get; set; } = 1500;

    /// <summary>
    /// Trả tiêu điểm về cửa sổ đứng trước lượt bắn (trình duyệt của thợ) — `SetTopMost Me, True`.
    /// Tắt nếu muốn để màn hình đứng lại ở ứng dụng pha màu sau khi bắn.
    /// </summary>
    public bool RestoreForeground { get; set; } = true;

    /// <summary>Đã đủ thông tin để đi tìm cửa sổ đích hay chưa.</summary>
    public bool CoCauHinhCuaSoDich
        => !string.IsNullOrWhiteSpace(TargetWindowTitle) || !string.IsNullOrWhiteSpace(TargetProcessName);
}

public class RackPoint
{
    public int X { get; set; }
    public int Y { get; set; }
}
