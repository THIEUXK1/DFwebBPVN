using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text;

namespace DFAgent;

/// <summary>
/// Tìm và đưa cửa sổ ứng dụng pha màu lên trước khi bắn rack — phần bị BỎ SÓT khi port
/// "SEND OVER 6" (xem ADR-012, mục cập nhật 07/08/2026).
///
/// ===== VÌ SAO PHẢI CÓ =====
/// Bản VBA gốc mở đầu cả `btn_Out_Click` lẫn `btn_In_Click` bằng:
///
///     SetTopMost Me, False
///     DoEvents
///     SmartDelay 150
///     ClickAt 10, 100
///
/// `SetTopMost Me, False` gỡ chính UserForm của Excel khỏi always-on-top, nên cửa sổ ứng dụng
/// pha màu lộ ra và cú `ClickAt 10, 100` rơi ĐÚNG vào nó. Bản port đầu tiên bỏ đoạn này với lý
/// do "Agent không có form nên không có gì để gỡ" — đúng về câu chữ, sai về hệ quả: cái đang che
/// ứng dụng pha màu bây giờ không phải form Excel mà là **trình duyệt chạy toàn màn hình (F11 /
/// kiosk)** của màn cân. Click (10,100) trúng trình duyệt, 6 lần Ctrl+V đổ vào trang web, mà
/// Agent vẫn báo DONE vì SetCursorPos/clipboard đều trả về thành công — kiểu báo thành công giả
/// tệ nhất: thợ tưởng đã cấp rack xong.
///
/// Lớp này thay `SetTopMost Me, False` bằng thao tác tương đương mà Agent làm được: tìm đúng cửa
/// sổ đích rồi đưa nó lên trước. Cặp đối xứng (`SetTopMost Me, True` — trả màn hình về cho thợ)
/// là việc khôi phục tiêu điểm cho cửa sổ đứng trước đó, xem <see cref="RackSender"/>.
/// </summary>
public static class WindowFocus
{
    private const int SW_RESTORE = 9;
    private const uint DWMWA_CLOAKED = 14;
    private const uint GA_ROOT = 2;
    private static readonly IntPtr HWND_BOTTOM = new(1);
    private const uint SWP_NOSIZE = 0x0001;
    private const uint SWP_NOMOVE = 0x0002;
    private const uint SWP_NOACTIVATE = 0x0010;

    /// <summary>
    /// Tiến trình KHÔNG bao giờ là ứng dụng pha màu: trình duyệt (chính là thứ đang che), vỏ
    /// desktop, và các cửa sổ hệ thống. Dùng để tự dò cửa sổ đích mà không cần ai khai báo gì.
    /// </summary>
    private static readonly HashSet<string> TrinhDuyet = new(StringComparer.OrdinalIgnoreCase)
    {
        "chrome", "msedge", "firefox", "brave", "opera", "vivaldi", "iexplore", "msedgewebview2",
    };

    private static readonly HashSet<string> VoDesktop = new(StringComparer.OrdinalIgnoreCase)
    {
        "explorer", "ApplicationFrameHost", "ShellExperienceHost", "SearchHost", "StartMenuExperienceHost",
    };

    /// <summary>Lớp cửa sổ của nền desktop và thanh tác vụ — click vào đây là click ra hư không.</summary>
    private static readonly HashSet<string> LopNenDesktop = new(StringComparer.OrdinalIgnoreCase)
    {
        "Progman", "WorkerW", "Shell_TrayWnd", "Shell_SecondaryTrayWnd", "Windows.UI.Core.CoreWindow",
    };

    private delegate bool EnumWindowsProc(IntPtr hwnd, IntPtr lParam);

    [DllImport("user32.dll")]
    private static extern bool EnumWindows(EnumWindowsProc lpEnumFunc, IntPtr lParam);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetWindowTextW(IntPtr hWnd, StringBuilder lpString, int nMaxCount);

    [DllImport("user32.dll")]
    private static extern int GetWindowTextLengthW(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool IsWindowVisible(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool IsIconic(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool BringWindowToTop(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern IntPtr GetForegroundWindow();

    [DllImport("user32.dll")]
    private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

    [DllImport("user32.dll")]
    private static extern bool AttachThreadInput(uint idAttach, uint idAttachTo, bool fAttach);

    [DllImport("user32.dll")]
    private static extern bool GetWindowRect(IntPtr hWnd, out RECT lpRect);

    [DllImport("kernel32.dll")]
    private static extern uint GetCurrentThreadId();

    [DllImport("dwmapi.dll")]
    private static extern int DwmGetWindowAttribute(IntPtr hwnd, uint dwAttribute, out int pvAttribute, int cbAttribute);

    [DllImport("user32.dll")]
    private static extern IntPtr WindowFromPoint(POINT Point);

    [DllImport("user32.dll")]
    private static extern IntPtr GetAncestor(IntPtr hwnd, uint gaFlags);

    [DllImport("user32.dll")]
    private static extern bool SetWindowPos(IntPtr hWnd, IntPtr hWndInsertAfter, int X, int Y, int cx, int cy, uint uFlags);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetClassNameW(IntPtr hWnd, StringBuilder lpClassName, int nMaxCount);

    [StructLayout(LayoutKind.Sequential)]
    private struct RECT
    {
        public int Left;
        public int Top;
        public int Right;
        public int Bottom;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct POINT
    {
        public int X;
        public int Y;
    }

    /// <summary>Một cửa sổ nhìn thấy được trên desktop của thợ.</summary>
    public sealed class CuaSo
    {
        public IntPtr Handle { get; init; }
        public string TieuDe { get; init; } = string.Empty;
        public string TienTrinh { get; init; } = string.Empty;
        public int Left { get; init; }
        public int Top { get; init; }
        public int Right { get; init; }
        public int Bottom { get; init; }

        public bool ChuaDiem(int x, int y) => x >= Left && x < Right && y >= Top && y < Bottom;

        public override string ToString()
            => $"\"{TieuDe}\" [{TienTrinh}.exe] tại ({Left},{Top})-({Right},{Bottom})";
    }

    public static IntPtr CuaSoDangHoatDong() => GetForegroundWindow();

    /// <summary>
    /// Mọi cửa sổ nhìn thấy được, TRỪ cửa sổ của chính Agent (nếu không thì cửa sổ console của
    /// Agent cũng lọt vào danh sách gợi ý và thợ có thể cấu hình nhầm chính nó làm đích).
    ///
    /// Bỏ qua cửa sổ "cloaked": UWP nào đã tắt vẫn để lại một cửa sổ hợp lệ nhưng bị hệ điều hành
    /// giấu (Cài đặt, Mail...). Chúng vẫn `IsWindowVisible` = true nên không lọc thì danh sách
    /// đầy cửa sổ ma, không dùng để đối chiếu được.
    /// </summary>
    public static List<CuaSo> LietKe()
    {
        var ketQua = new List<CuaSo>();
        uint tienTrinhCuaAgent = (uint) Environment.ProcessId;

        EnumWindows((hwnd, _) =>
        {
            if (!IsWindowVisible(hwnd)) return true;

            int doDai = GetWindowTextLengthW(hwnd);
            if (doDai <= 0) return true;

            if (DwmGetWindowAttribute(hwnd, DWMWA_CLOAKED, out int bịGiau, sizeof(int)) == 0 && bịGiau != 0)
                return true;

            GetWindowThreadProcessId(hwnd, out uint pid);
            if (pid == tienTrinhCuaAgent) return true;

            var sb = new StringBuilder(doDai + 1);
            GetWindowTextW(hwnd, sb, sb.Capacity);

            GetWindowRect(hwnd, out RECT r);

            ketQua.Add(new CuaSo
            {
                Handle = hwnd,
                TieuDe = sb.ToString(),
                TienTrinh = TenTienTrinh(pid),
                Left = r.Left,
                Top = r.Top,
                Right = r.Right,
                Bottom = r.Bottom,
            });

            return true;
        }, IntPtr.Zero);

        return ketQua;
    }

    /// <summary>
    /// Tìm cửa sổ đích theo MỘT PHẦN tiêu đề và/hoặc tên tiến trình.
    ///
    /// Khai báo cả hai thì phải khớp CẢ HAI — hẹp hơn, và đó là điều mong muốn: bắn nhầm cửa sổ
    /// nghĩa là dán mã rack vào ứng dụng bất kỳ. Khớp tiêu đề theo chuỗi con vì tiêu đề ứng dụng
    /// pha màu thường có thêm phần đuôi thay đổi (tên mẻ, số phiên bản).
    /// </summary>
    public static List<CuaSo> Tim(string? phanTieuDe, string? tenTienTrinh)
    {
        bool coTieuDe = !string.IsNullOrWhiteSpace(phanTieuDe);
        bool coTienTrinh = !string.IsNullOrWhiteSpace(tenTienTrinh);
        if (!coTieuDe && !coTienTrinh) return new List<CuaSo>();

        string tieuDe = (phanTieuDe ?? string.Empty).Trim();
        string tienTrinh = (tenTienTrinh ?? string.Empty).Trim();
        if (tienTrinh.EndsWith(".exe", StringComparison.OrdinalIgnoreCase))
            tienTrinh = tienTrinh[..^4];

        return LietKe().Where(w =>
            (!coTieuDe || w.TieuDe.Contains(tieuDe, StringComparison.OrdinalIgnoreCase)) &&
            (!coTienTrinh || string.Equals(w.TienTrinh, tienTrinh, StringComparison.OrdinalIgnoreCase))
        ).ToList();
    }

    /// <summary>
    /// Cửa sổ nào đang NẰM NGAY DƯỚI CON TRỎ tại các toạ độ sắp click — nền tảng của việc tự dò
    /// cửa sổ đích, để thợ **không phải khai báo gì cả**.
    ///
    /// Bản VBA gốc cũng không hề biết ứng dụng pha màu tên gì: `SetTopMost Me, False` chỉ đẩy cái
    /// đang che (form Excel) xuống, rồi click thẳng vào toạ độ — cái gì nằm ở đó thì nhận. Hàm này
    /// làm đúng việc "cái gì nằm ở đó", chỉ khác là hỏi trước khi click thay vì click rồi mới biết.
    ///
    /// Bỏ phiếu theo đa số các điểm chứ không tin một điểm duy nhất: một toạ độ lẻ có thể rơi trúng
    /// mép cửa sổ khác hoặc một popup đang bung, nhưng cả 9 điểm cùng trỏ về một cửa sổ thì đó
    /// chính là ứng dụng đích.
    ///
    /// Bỏ qua nền desktop, thanh tác vụ và chính Agent — click vào mấy thứ đó là click ra hư không.
    /// KHÔNG bỏ qua trình duyệt ở đây: người gọi cần biết trình duyệt đang che để còn đẩy nó xuống.
    /// </summary>
    public static CuaSo? CuaSoTaiCacDiem(IReadOnlyList<(int X, int Y)> diem)
    {
        var danhSach = LietKe().ToDictionary(w => w.Handle);
        var phieu = new Dictionary<IntPtr, int>();

        foreach (var (x, y) in diem)
        {
            IntPtr hwnd = WindowFromPoint(new POINT { X = x, Y = y });
            if (hwnd == IntPtr.Zero) continue;

            // WindowFromPoint trả về control con (ô nhập, nút) — leo lên cửa sổ gốc mới so được
            // với danh sách cửa sổ cấp trên.
            IntPtr goc = GetAncestor(hwnd, GA_ROOT);
            if (goc == IntPtr.Zero) goc = hwnd;

            if (!danhSach.ContainsKey(goc)) continue;   // cửa sổ của chính Agent, hoặc cửa sổ ẩn
            if (LaNenDesktop(goc)) continue;
            if (VoDesktop.Contains(danhSach[goc].TienTrinh)) continue;

            phieu[goc] = phieu.GetValueOrDefault(goc) + 1;
        }

        if (phieu.Count == 0) return null;

        IntPtr thangCuoc = phieu.OrderByDescending(p => p.Value).First().Key;
        return danhSach[thangCuoc];
    }

    /// <summary>Cửa sổ này là của trình duyệt — tức là thứ ĐANG CHE, không bao giờ là đích.</summary>
    public static bool LaTrinhDuyet(CuaSo w) => TrinhDuyet.Contains(w.TienTrinh);

    /// <summary>
    /// Đẩy cửa sổ xuống đáy thứ tự chồng — đây mới là bản dịch sát nhất của `SetTopMost Me, False`.
    ///
    /// Cố ý KHÔNG thu nhỏ (`SW_MINIMIZE`): bản gốc chỉ bỏ always-on-top chứ không thu nhỏ form, và
    /// thu nhỏ trình duyệt thì có nguy cơ nó bung ra khỏi chế độ toàn màn hình lúc khôi phục — đúng
    /// cái phiền mà màn cân đã mất công dẹp (xem mục 125, 127, 130 trong session-log).
    /// `SWP_NOACTIVATE` để việc đẩy xuống không tự kéo cửa sổ khác lên làm cửa sổ hoạt động.
    /// </summary>
    public static void DayXuongDay(IntPtr hwnd)
    {
        if (hwnd != IntPtr.Zero)
            SetWindowPos(hwnd, HWND_BOTTOM, 0, 0, 0, 0, SWP_NOMOVE | SWP_NOSIZE | SWP_NOACTIVATE);
    }

    private static bool LaNenDesktop(IntPtr hwnd)
    {
        var sb = new StringBuilder(256);
        GetClassNameW(hwnd, sb, sb.Capacity);
        return LopNenDesktop.Contains(sb.ToString());
    }

    /// <summary>
    /// Đưa cửa sổ lên trước và CHỜ CHO ĐẾN KHI nó thật sự ở trước — tương đương
    /// `SetTopMost Me, False` + `DoEvents` của bản gốc.
    ///
    /// `SetForegroundWindow` một mình KHÔNG đủ: Windows chặn tiến trình không đang giữ tiêu điểm
    /// giành tiêu điểm (chống cướp màn hình). Cách vượt qua hợp lệ và dùng phổ biến là tạm nối
    /// hàng đợi nhập của luồng Agent vào luồng đang giữ tiêu điểm (`AttachThreadInput`) — trong
    /// lúc nối, Agent được coi là "cùng phe" với cửa sổ đang hoạt động nên lệnh được chấp nhận.
    ///
    /// Chờ có xác minh chứ không chờ mù bằng SmartDelay: đây chính là cổng an toàn thay cho việc
    /// bắn mù trước đây. Không lên được thì thà báo FAILED.
    /// </summary>
    public static bool DuaLenTruoc(IntPtr hwnd, int hetHanMs)
    {
        if (hwnd == IntPtr.Zero) return false;

        if (IsIconic(hwnd)) ShowWindow(hwnd, SW_RESTORE);

        IntPtr dangTruoc = GetForegroundWindow();
        uint luongDangTruoc = dangTruoc == IntPtr.Zero ? 0 : GetWindowThreadProcessId(dangTruoc, out _);
        uint luongCuaTa = GetCurrentThreadId();

        bool daNoi = luongDangTruoc != 0
                     && luongDangTruoc != luongCuaTa
                     && AttachThreadInput(luongCuaTa, luongDangTruoc, true);
        try
        {
            BringWindowToTop(hwnd);
            SetForegroundWindow(hwnd);
        }
        finally
        {
            if (daNoi) AttachThreadInput(luongCuaTa, luongDangTruoc, false);
        }

        DateTime hetHan = DateTime.UtcNow.AddMilliseconds(Math.Max(hetHanMs, 100));
        while (DateTime.UtcNow < hetHan)
        {
            if (DangODangTruoc(hwnd)) return true;
            Thread.Sleep(30);
        }

        return DangODangTruoc(hwnd);
    }

    /// <summary>
    /// Cửa sổ đích đã ở trước chưa. Tính CẢ trường hợp cửa sổ đang hoạt động là một cửa sổ khác
    /// của CÙNG tiến trình — ứng dụng pha màu bật hộp thoại con thì tiêu điểm nằm ở hộp thoại,
    /// nhưng đứng từ phía thợ thì ứng dụng đó vẫn đang ở trước và toạ độ click vẫn đúng.
    /// </summary>
    private static bool DangODangTruoc(IntPtr hwnd)
    {
        IntPtr truoc = GetForegroundWindow();
        if (truoc == hwnd) return true;
        if (truoc == IntPtr.Zero) return false;

        GetWindowThreadProcessId(truoc, out uint pidTruoc);
        GetWindowThreadProcessId(hwnd, out uint pidDich);
        return pidTruoc != 0 && pidTruoc == pidDich;
    }

    /// <summary>Danh sách cửa sổ đang mở, viết gọn để đổ vào log cho thợ đối chiếu mà cấu hình.</summary>
    public static string MoTaDanhSach(IEnumerable<CuaSo> danhSach)
    {
        var sb = new StringBuilder();
        foreach (var w in danhSach) sb.AppendLine("      - " + w);
        return sb.Length == 0 ? "      (không có cửa sổ nào)" : sb.ToString().TrimEnd();
    }

    private static string TenTienTrinh(uint pid)
    {
        try
        {
            using var p = Process.GetProcessById((int) pid);
            return p.ProcessName;
        }
        catch
        {
            // Tiến trình hệ thống hoặc chạy quyền cao hơn — không đọc được tên thì vẫn phải liệt
            // kê cửa sổ, vì có thể chính nó là đích.
            return "?";
        }
    }
}
