using System;
using System.Runtime.InteropServices;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Hosting.WindowsServices;
using Microsoft.Extensions.Logging;

namespace DFAgent;

/// <summary>Cấu hình khay hệ thống — mục "Tray" trong appsettings.json.</summary>
public sealed class TrayOptions
{
    /// <summary>
    /// Chỉ bản CHẠY TRONG PHIÊN NGƯỜI DÙNG (Can to - IN/OUT) mới bật. Mặc định TẮT: hai bản
    /// kia chạy dưới Windows Service ở session 0 — nơi không có thanh tác vụ nào để mà hiện
    /// biểu tượng, gọi Shell_NotifyIcon ở đó chỉ tốn công vô ích.
    /// </summary>
    public bool Enabled { get; set; }

    /// <summary>Vào là ẩn luôn cửa sổ console, chỉ còn biểu tượng khay (đúng kiểu WeChat).</summary>
    public bool StartHidden { get; set; } = true;

    /// <summary>Chữ hiện khi rê chuột lên biểu tượng. Để trống thì lấy theo Service:Name.</summary>
    public string Title { get; set; } = string.Empty;
}

/// <summary>
/// Cho bản IN/OUT sống ở KHAY HỆ THỐNG (góc phải dưới) thay vì một cửa sổ console trên thanh
/// tác vụ — yêu cầu người dùng 08/08/2026: *"tôi muốn là loại ra dù tắt x thì vẫn là chạy ngầm
/// như wechat ấy"*.
///
/// ===== VÌ SAO CẦN =====
/// Bản IN/OUT bắt buộc chạy trong phiên đăng nhập của thợ (session 0 isolation — xem ghi chú
/// RunMode trong DFAgentSetup.wxs), nên nó xuất hiện như một cửa sổ console. Thợ thấy cửa sổ lạ
/// thì bấm ✕, và thế là nút IN/OUT trên `/weighing-station-large` chết câm cho tới khi có người
/// biết đường bật lại bằng shortcut trong Start Menu.
///
/// ===== VÌ SAO KHÔNG "BẮT SỰ KIỆN BẤM ✕ RỒI ẨN ĐI" =====
/// Không làm được với ứng dụng console. Cửa sổ console thuộc về conhost.exe chứ không phải tiến
/// trình này, nên không subclass được; còn `SetConsoleCtrlHandler`/CTRL_CLOSE_EVENT thì chỉ cho
/// dọn dẹp chứ KHÔNG hủy được lệnh đóng — hàm xử lý chạy xong là Windows giết tiến trình.
/// Vì vậy làm theo hướng ngược lại, và cũng đúng kiểu WeChat hơn:
///   1. Vào là ẩn hẳn cửa sổ console — không còn cái ✕ nào để bấm nhầm.
///   2. Agent nằm ở khay hệ thống: bấm đúp để xem nhật ký, chuột phải để ẩn/hiện hoặc thoát hẳn.
///   3. Lúc cửa sổ nhật ký đang hiện thì GỠ luôn mục Close khỏi menu hệ thống của nó (nút ✕ xám
///      đi), để lỡ tay bấm cũng không tắt mất Agent. Muốn tắt phải vào khay hệ thống — tức là
///      phải cố ý.
///
/// ===== TỰ VIẾT P/INVOKE, KHÔNG DÙNG WINFORMS =====
/// `NotifyIcon` của WinForms sẽ kéo theo `net8.0-windows` + `UseWindowsForms`, mà bản publish là
/// self-contained DÙNG CHUNG cho cả ba bộ cài — hai bộ đọc cân (chạy service, không bao giờ có
/// giao diện) cũng phải gánh thêm mấy chục MB Windows Desktop runtime. Shell_NotifyIcon trần thì
/// không thêm một byte phụ thuộc nào.
/// </summary>
public sealed class TrayIcon : IHostedService
{
    private const int WM_DESTROY = 0x0002;
    private const int WM_CLOSE = 0x0010;
    private const int WM_NULL = 0x0000;
    private const int WM_LBUTTONDBLCLK = 0x0203;
    private const int WM_RBUTTONUP = 0x0205;
    private const int WM_CONTEXTMENU = 0x007B;
    private const int WM_TRAY = 0x0400 + 1;   // WM_APP+1: mã tự chọn, chỉ cửa sổ này nhận

    private const int NIM_ADD = 0x00000000;
    private const int NIM_MODIFY = 0x00000001;
    private const int NIM_DELETE = 0x00000002;
    private const int NIF_MESSAGE = 0x00000001;
    private const int NIF_ICON = 0x00000002;
    private const int NIF_TIP = 0x00000004;
    private const int NIF_INFO = 0x00000010;
    private const int NIIF_INFO = 0x00000001;

    private const int MF_STRING = 0x00000000;
    private const int MF_SEPARATOR = 0x00000800;
    private const int MF_BYCOMMAND = 0x00000000;
    private const uint TPM_RIGHTBUTTON = 0x0002;
    private const uint TPM_RETURNCMD = 0x0100;
    private const uint TPM_NONOTIFY = 0x0080;

    private const int SW_HIDE = 0;
    private const int SW_SHOW = 5;
    private const int SW_RESTORE = 9;
    private const int SC_CLOSE = 0xF060;

    private const uint MB_OK = 0x00000000;
    private const uint MB_YESNO = 0x00000004;
    private const uint MB_ICONWARNING = 0x00000030;
    private const uint MB_ICONINFORMATION = 0x00000040;
    private const uint MB_SETFOREGROUND = 0x00010000;
    private const int IDYES = 6;

    private const int MENU_CUA_SO = 1;
    private const int MENU_THOAT = 2;

    private readonly ILogger<TrayIcon> _logger;
    private readonly IHostApplicationLifetime _lifetime;
    private readonly bool _enabled;
    private readonly bool _startHidden;
    private readonly string _tieuDe;

    private Thread? _luong;
    private IntPtr _hwnd = IntPtr.Zero;
    private IntPtr _hConsole = IntPtr.Zero;
    private IntPtr _hIcon = IntPtr.Zero;
    private uint _msgTaskbarCreated;
    private bool _dangHienCuaSo = true;
    private bool _lamChuConsole;

    // Delegate PHẢI giữ trong một trường: truyền thẳng vào RegisterClass thì bộ gom rác dọn nó
    // ngay sau lời gọi, và cú gọi ngược đầu tiên từ Windows sẽ nhảy vào vùng nhớ đã chết.
    private WndProcDelegate? _wndProc;

    private readonly ManualResetEventSlim _dungXong = new(false);

    public TrayIcon(
        ILogger<TrayIcon> logger,
        IHostApplicationLifetime lifetime,
        IConfiguration config,
        TrayOptions options)
    {
        _logger = logger;
        _lifetime = lifetime;

        // Chạy dưới Service thì tuyệt đối không đụng tới: session 0 không có thanh tác vụ, và
        // MessageBox mở ra ở đó là hộp thoại không ai thấy để bấm.
        _enabled = options.Enabled && !WindowsServiceHelpers.IsWindowsService();
        _startHidden = options.StartHidden;
        _tieuDe = string.IsNullOrWhiteSpace(options.Title)
            ? $"DF Local Agent ({config.GetValue<string>("Service:Name") ?? "DFAgent"})"
            : options.Title.Trim();
    }

    public Task StartAsync(CancellationToken cancellationToken)
    {
        if (!_enabled) return Task.CompletedTask;

        // STA: TrackPopupMenu và vùng thông báo của shell đều mong đợi luồng giao diện kiểu này.
        // Bọc trong kiểm tra hệ điều hành cho phân tích tĩnh (CA1416) — dự án nhắm `net8.0` trơn
        // để hai bản chạy service không phải kéo theo Windows Desktop runtime.
        _luong = new Thread(VongThongDiep) { IsBackground = true, Name = "DFAgent-Tray" };
        if (OperatingSystem.IsWindows()) _luong.SetApartmentState(ApartmentState.STA);
        _luong.Start();

        return Task.CompletedTask;
    }

    public Task StopAsync(CancellationToken cancellationToken)
    {
        if (!_enabled || _luong is null) return Task.CompletedTask;

        if (_hwnd != IntPtr.Zero)
        {
            XoaBieuTuong();
            PostMessageW(_hwnd, WM_CLOSE, IntPtr.Zero, IntPtr.Zero);
        }

        // Không chờ mãi: mất biểu tượng khay không đáng để treo cả tiến trình lúc tắt.
        _dungXong.Wait(TimeSpan.FromSeconds(2));
        return Task.CompletedTask;
    }

    private void VongThongDiep()
    {
        try
        {
            ChuanBiCuaSoConsole();

            if (!TaoCuaSoAn())
            {
                _logger.LogWarning("Khong tao duoc cua so an cho khay he thong — Agent van chay binh thuong, chi la khong co bieu tuong o goc phai duoi.");
                return;
            }

            _hIcon = LayBieuTuong();
            _msgTaskbarCreated = RegisterWindowMessageW("TaskbarCreated");

            if (!ThemBieuTuong())
            {
                _logger.LogWarning("Shell_NotifyIcon that bai — khong hien duoc bieu tuong khay. Neu cua so console dang an, mo lai bang shortcut trong Start Menu.");
                // Không có biểu tượng khay mà cửa sổ vẫn ẩn là mất hẳn đường vào — trả cửa sổ lại.
                HienCuaSo(true);
                return;
            }

            _logger.LogInformation("Khay he thong: Agent chay ngam o goc phai duoi. Bam dup de xem nhat ky, chuot phai de an/hien hoac thoat han.");
            if (!_dangHienCuaSo) BongThongBao();

            while (GetMessageW(out MSG msg, IntPtr.Zero, 0, 0) > 0)
            {
                TranslateMessage(ref msg);
                DispatchMessageW(ref msg);
            }
        }
        catch (Exception ex)
        {
            // Không để một lỗi giao diện nào giết Agent: việc chính của nó là lấy lệnh IN/OUT.
            _logger.LogError("Loi o luong khay he thong: {Msg}. Agent van chay.", ex.Message);
            HienCuaSo(true);
        }
        finally
        {
            XoaBieuTuong();
            _dungXong.Set();
        }
    }

    /// <summary>
    /// Ẩn cửa sổ console và gỡ nút ✕ của nó — CHỈ khi console này là của riêng Agent.
    ///
    /// Kiểm tra bằng <c>GetConsoleProcessList</c>: chạy bằng shortcut thì Agent là tiến trình duy
    /// nhất gắn với console đó. Còn khi lập trình viên chạy `dotnet run` từ cmd/Terminal thì
    /// console là của cmd (có từ 2 tiến trình trở lên) — ẩn nó đi là giấu mất cửa sổ của chính
    /// người đang gõ lệnh, và gỡ nút ✕ của họ luôn.
    /// </summary>
    private void ChuanBiCuaSoConsole()
    {
        _hConsole = GetConsoleWindow();
        if (_hConsole == IntPtr.Zero) return;

        _lamChuConsole = ConsoleRiengCuaTa();
        if (!_lamChuConsole)
        {
            _logger.LogInformation("Console dang dung chung voi tien trinh khac (chay tu cmd/Terminal) — khong an, khong go nut dong.");
            return;
        }

        SetConsoleTitleW($"{_tieuDe} - nhat ky (dong cua so o khay he thong, goc phai duoi)");

        IntPtr menu = GetSystemMenu(_hConsole, false);
        if (menu != IntPtr.Zero)
        {
            DeleteMenu(menu, SC_CLOSE, MF_BYCOMMAND);
            DrawMenuBar(_hConsole);
        }

        if (_startHidden)
        {
            ShowWindow(_hConsole, SW_HIDE);
            _dangHienCuaSo = false;
        }
    }

    private bool ConsoleRiengCuaTa()
    {
        var danhSach = new uint[8];
        uint soLuong = GetConsoleProcessList(danhSach, (uint) danhSach.Length);
        return soLuong == 1;
    }

    private bool TaoCuaSoAn()
    {
        string lopCuaSo = "DFAgentTrayWindow";
        _wndProc = XuLyThongDiep;

        var lop = new WNDCLASS
        {
            lpfnWndProc = Marshal.GetFunctionPointerForDelegate(_wndProc),
            hInstance = GetModuleHandleW(null),
            lpszClassName = lopCuaSo,
        };

        // 0 = đã đăng ký rồi (không xảy ra vì chỉ một bản chạy), vẫn tạo cửa sổ tiếp được.
        RegisterClassW(ref lop);

        // Cửa sổ KHÔNG BAO GIỜ hiện (không có WS_VISIBLE) — nó chỉ tồn tại để nhận thông điệp
        // chuột từ biểu tượng khay. Cố ý không dùng cửa sổ message-only (HWND_MESSAGE): loại đó
        // không làm cửa sổ tiền cảnh được nên menu chuột phải sẽ không tự đóng khi bấm ra ngoài.
        _hwnd = CreateWindowExW(0, lopCuaSo, _tieuDe, 0, 0, 0, 0, 0,
                                IntPtr.Zero, IntPtr.Zero, GetModuleHandleW(null), IntPtr.Zero);
        return _hwnd != IntPtr.Zero;
    }

    private IntPtr XuLyThongDiep(IntPtr hwnd, uint msg, IntPtr wParam, IntPtr lParam)
    {
        // Explorer khởi động lại (treo, hoặc thợ tự kill) là mọi biểu tượng khay biến mất. Không
        // thêm lại thì Agent vẫn chạy nhưng không còn đường nào vào — trông y như đã tắt.
        if (_msgTaskbarCreated != 0 && msg == _msgTaskbarCreated)
        {
            ThemBieuTuong();
            return IntPtr.Zero;
        }

        switch (msg)
        {
            case WM_TRAY:
                int suKien = (int) (lParam.ToInt64() & 0xFFFF);
                if (suKien == WM_LBUTTONDBLCLK) HienCuaSo(!_dangHienCuaSo);
                else if (suKien is WM_RBUTTONUP or WM_CONTEXTMENU) MoMenu();
                return IntPtr.Zero;

            case WM_CLOSE:
                DestroyWindow(hwnd);
                return IntPtr.Zero;

            case WM_DESTROY:
                PostQuitMessage(0);
                return IntPtr.Zero;
        }

        return DefWindowProcW(hwnd, msg, wParam, lParam);
    }

    private void MoMenu()
    {
        GetCursorPos(out POINT chuot);

        // Bắt buộc: không đưa cửa sổ này lên tiền cảnh trước thì menu sẽ đứng lại trên màn hình
        // khi thợ bấm ra chỗ khác (lỗi kinh điển của Shell_NotifyIcon, KB135788).
        SetForegroundWindow(_hwnd);

        IntPtr menu = CreatePopupMenu();
        // Không có console riêng (chạy từ cmd/Terminal, hoặc bị chuyển hướng đầu ra) thì không
        // bày mục ẩn/hiện: bấm vào cũng không có cửa sổ nào để ẩn.
        if (_hConsole != IntPtr.Zero && _lamChuConsole)
        {
            AppendMenuW(menu, MF_STRING, MENU_CUA_SO, _dangHienCuaSo ? "Ẩn cửa sổ nhật ký" : "Hiện cửa sổ nhật ký");
            AppendMenuW(menu, MF_SEPARATOR, 0, null);
        }
        AppendMenuW(menu, MF_STRING, MENU_THOAT, "Thoát hẳn (dừng IN/OUT)");

        uint chon = TrackPopupMenuEx(menu, TPM_RIGHTBUTTON | TPM_RETURNCMD | TPM_NONOTIFY,
                                     chuot.X, chuot.Y, _hwnd, IntPtr.Zero);
        DestroyMenu(menu);
        PostMessageW(_hwnd, WM_NULL, IntPtr.Zero, IntPtr.Zero);   // cùng lý do với KB135788

        if (chon == MENU_CUA_SO) HienCuaSo(!_dangHienCuaSo);
        else if (chon == MENU_THOAT) XinThoat();
    }

    /// <summary>
    /// Hỏi lại trước khi thoát: tắt Agent này là nút IN/OUT của `/weighing-station-large` chết
    /// câm (màn hình chỉ báo "Agent trên máy trạm CHƯA lấy lệnh" sau ~12 giây), mà đây lại là
    /// một menu chuột phải rất dễ bấm nhầm.
    /// </summary>
    private void XinThoat()
    {
        int tra = MessageBoxW(_hwnd,
            "Thoát hẳn Agent IN/OUT?\n\n" +
            "Nút IN/OUT trên màn hình cân to sẽ không gửi được mã rack sang hệ pha màu cho tới khi " +
            "bật lại (Start Menu > DF Local Agent).",
            _tieuDe,
            MB_YESNO | MB_ICONWARNING | MB_SETFOREGROUND);

        if (tra != IDYES) return;

        _logger.LogInformation("Nguoi dung chon Thoat tu khay he thong — dung Agent.");
        _lifetime.StopApplication();
    }

    /// <summary>
    /// Hộp thoại báo cho người đứng máy, dùng khi CHƯA có gì trên màn hình để nói với họ — cụ
    /// thể là lúc chặn bản chạy trùng (xem Program.ChiMotBanChay). Ở chế độ khay, cửa sổ console
    /// bị ẩn nên viết ra log là viết cho không ai đọc.
    /// </summary>
    public static void NhacNguoiDung(string noiDung, string tieuDe)
        => MessageBoxW(IntPtr.Zero, noiDung, tieuDe, MB_OK | MB_ICONINFORMATION | MB_SETFOREGROUND);

    private void HienCuaSo(bool hien)
    {
        if (_hConsole == IntPtr.Zero || !_lamChuConsole) return;

        ShowWindow(_hConsole, hien ? SW_SHOW : SW_HIDE);
        if (hien)
        {
            ShowWindow(_hConsole, SW_RESTORE);
            SetForegroundWindow(_hConsole);
        }
        _dangHienCuaSo = hien;
    }

    private NOTIFYICONDATA DuLieuBieuTuong(int flags)
        => new()
        {
            cbSize = Marshal.SizeOf<NOTIFYICONDATA>(),
            hWnd = _hwnd,
            uID = 1,
            uFlags = flags,
            uCallbackMessage = WM_TRAY,
            hIcon = _hIcon,
            szTip = _tieuDe.Length > 120 ? _tieuDe[..120] : _tieuDe,
            szInfo = string.Empty,
            szInfoTitle = string.Empty,
        };

    private bool ThemBieuTuong()
    {
        var data = DuLieuBieuTuong(NIF_MESSAGE | NIF_ICON | NIF_TIP);
        return Shell_NotifyIconW(NIM_ADD, ref data);
    }

    private void XoaBieuTuong()
    {
        if (_hwnd == IntPtr.Zero) return;
        var data = DuLieuBieuTuong(0);
        Shell_NotifyIconW(NIM_DELETE, ref data);
    }

    /// <summary>Bóng thông báo một lần lúc khởi động — để thợ biết Agent vừa chui vào đâu.</summary>
    private void BongThongBao()
    {
        var data = DuLieuBieuTuong(NIF_INFO);
        data.szInfoTitle = "DF Local Agent đang chạy ngầm";
        data.szInfo = "Agent nằm ở khay hệ thống (góc phải dưới). Bấm đúp để xem nhật ký; chuột phải để thoát.";
        data.dwInfoFlags = NIIF_INFO;
        Shell_NotifyIconW(NIM_MODIFY, ref data);
    }

    /// <summary>
    /// Biểu tượng của chính file .exe; không lấy được thì dùng biểu tượng ứng dụng mặc định của
    /// Windows. Không có biểu tượng thì vùng khay hiện một ô trống — vẫn bấm được nhưng thợ
    /// không nhận ra là cái gì.
    /// </summary>
    private IntPtr LayBieuTuong()
    {
        try
        {
            string? exe = Environment.ProcessPath;
            if (!string.IsNullOrEmpty(exe))
            {
                IntPtr icon = ExtractIconW(GetModuleHandleW(null), exe, 0);
                // ExtractIcon trả về 1 khi file không chứa biểu tượng nào.
                if (icon != IntPtr.Zero && icon != new IntPtr(1)) return icon;
            }
        }
        catch { /* rơi xuống biểu tượng mặc định bên dưới */ }

        return LoadIconW(IntPtr.Zero, new IntPtr(32512));   // IDI_APPLICATION
    }

    // ===================== P/Invoke =====================

    private delegate IntPtr WndProcDelegate(IntPtr hwnd, uint msg, IntPtr wParam, IntPtr lParam);

    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct WNDCLASS
    {
        public uint style;
        public IntPtr lpfnWndProc;
        public int cbClsExtra;
        public int cbWndExtra;
        public IntPtr hInstance;
        public IntPtr hIcon;
        public IntPtr hCursor;
        public IntPtr hbrBackground;
        [MarshalAs(UnmanagedType.LPWStr)] public string? lpszMenuName;
        [MarshalAs(UnmanagedType.LPWStr)] public string lpszClassName;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct POINT
    {
        public int X;
        public int Y;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct MSG
    {
        public IntPtr hwnd;
        public uint message;
        public IntPtr wParam;
        public IntPtr lParam;
        public uint time;
        public POINT pt;
    }

    /// <summary>
    /// Bố cục PHẢI đúng thứ tự và đúng độ dài chuỗi của NOTIFYICONDATAW (shellapi.h) — sai một
    /// trường là Shell_NotifyIcon trả về false mà không nói vì sao.
    /// </summary>
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct NOTIFYICONDATA
    {
        public int cbSize;
        public IntPtr hWnd;
        public int uID;
        public int uFlags;
        public int uCallbackMessage;
        public IntPtr hIcon;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 128)] public string szTip;
        public int dwState;
        public int dwStateMask;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 256)] public string szInfo;
        public int uTimeoutOrVersion;
        [MarshalAs(UnmanagedType.ByValTStr, SizeConst = 64)] public string szInfoTitle;
        public int dwInfoFlags;
        public Guid guidItem;
        public IntPtr hBalloonIcon;
    }

    [DllImport("shell32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern bool Shell_NotifyIconW(int dwMessage, ref NOTIFYICONDATA lpData);

    [DllImport("shell32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr ExtractIconW(IntPtr hInst, string lpszExeFileName, int nIconIndex);

    [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern ushort RegisterClassW(ref WNDCLASS lpWndClass);

    [DllImport("user32.dll", CharSet = CharSet.Unicode, SetLastError = true)]
    private static extern IntPtr CreateWindowExW(uint dwExStyle, string lpClassName, string lpWindowName,
        uint dwStyle, int x, int y, int nWidth, int nHeight, IntPtr hWndParent, IntPtr hMenu,
        IntPtr hInstance, IntPtr lpParam);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr DefWindowProcW(IntPtr hWnd, uint msg, IntPtr wParam, IntPtr lParam);

    [DllImport("user32.dll")]
    private static extern bool DestroyWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern void PostQuitMessage(int nExitCode);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int GetMessageW(out MSG lpMsg, IntPtr hWnd, uint wMsgFilterMin, uint wMsgFilterMax);

    [DllImport("user32.dll")]
    private static extern bool TranslateMessage(ref MSG lpMsg);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr DispatchMessageW(ref MSG lpMsg);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern bool PostMessageW(IntPtr hWnd, uint msg, IntPtr wParam, IntPtr lParam);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern uint RegisterWindowMessageW(string lpString);

    [DllImport("user32.dll")]
    private static extern IntPtr CreatePopupMenu();

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern bool AppendMenuW(IntPtr hMenu, int uFlags, int uIDNewItem, string? lpNewItem);

    [DllImport("user32.dll")]
    private static extern bool DestroyMenu(IntPtr hMenu);

    [DllImport("user32.dll")]
    private static extern uint TrackPopupMenuEx(IntPtr hMenu, uint uFlags, int x, int y, IntPtr hwnd, IntPtr lptpm);

    [DllImport("user32.dll")]
    private static extern bool GetCursorPos(out POINT lpPoint);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    private static extern IntPtr GetSystemMenu(IntPtr hWnd, bool bRevert);

    [DllImport("user32.dll")]
    private static extern bool DeleteMenu(IntPtr hMenu, int nPosition, int wFlags);

    [DllImport("user32.dll")]
    private static extern bool DrawMenuBar(IntPtr hWnd);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr LoadIconW(IntPtr hInstance, IntPtr lpIconName);

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int MessageBoxW(IntPtr hWnd, string lpText, string lpCaption, uint uType);

    [DllImport("kernel32.dll")]
    private static extern IntPtr GetConsoleWindow();

    [DllImport("kernel32.dll")]
    private static extern uint GetConsoleProcessList(uint[] lpdwProcessList, uint dwProcessCount);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
    private static extern bool SetConsoleTitleW(string lpConsoleTitle);

    [DllImport("kernel32.dll", CharSet = CharSet.Unicode)]
    private static extern IntPtr GetModuleHandleW(string? lpModuleName);
}
