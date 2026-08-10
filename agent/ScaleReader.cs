using System;
using System.IO;
using System.IO.Ports;
using System.Linq;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class ScaleReader : IDisposable
{
    // Đường dẫn file log PuTTY trên máy trạm cân (xác nhận với người dùng 2026-08-01).
    public const string DefaultLogFilePath = @"D:\scale\putty_log.txt";

    private readonly ILogger<ScaleReader> _logger;
    private readonly IConfiguration _config;
    private SerialPort? _serialPort;
    private readonly string[] _logFilePaths;
    private readonly bool _useSimulation;

    public ScaleReader(ILogger<ScaleReader> logger, IConfiguration config)
    {
        _logger = logger;
        _config = config;
        // Scale:Source là cờ chính thức (2026-08-01): "PUTTY_LOG" = đọc đuôi file log PuTTY,
        // đúng cách hệ Excel VBA cũ vẫn chạy ở xưởng; "SERIAL" = mở thẳng cổng COM.
        //
        // Trước đây chỉ có cờ UseSimulation, đặt tên sai bản chất: đọc file PuTTY là cách vận
        // hành THẬT của xưởng suốt nhiều năm, không phải chế độ giả lập. Người vận hành phải bật
        // một cờ tên "UseSimulation" để chạy sản xuất thật là chỗ rất dễ hiểu nhầm và dễ bị ai đó
        // tắt đi vì tưởng là đồ demo. Vẫn đọc cờ cũ làm dự phòng để máy đã cài không đổi hành vi
        // sau khi cập nhật Agent.
        string? source = _config.GetValue<string>("Scale:Source");
        _useSimulation = string.IsNullOrWhiteSpace(source)
            ? _config.GetValue<bool>("Scale:UseSimulation", true)
            : !string.Equals(source, "SERIAL", StringComparison.OrdinalIgnoreCase);

        _logFilePaths = ResolveLogFilePaths(_config);

        if (!_useSimulation)
        {
            InitializeSerialPort();
        }
        else
        {
            _logger.LogInformation(
                "Đọc cân từ file log PuTTY (cách cũ, giống Excel VBA). Thứ tự ưu tiên: {Paths}",
                string.Join(" -> ", _logFilePaths));
        }
    }

    /// <summary>
    /// Danh sách file log PuTTY sẽ đọc, THEO THỨ TỰ ƯU TIÊN. Luôn trả về ít nhất 1 phần tử.
    ///
    /// `Scale:LogFilePath` là khoá chính thức; `Scale:SimulationFilePath` giữ làm dự phòng cho
    /// cấu hình cũ trên máy đã cài (cùng lý do với Source/UseSimulation ở trên — file log PuTTY
    /// là đường chạy THẬT của xưởng, không phải đồ giả lập).
    ///
    /// `Scale:LogFilePathFallback` (2026-08-04) chỉ có ý nghĩa với bản CÂN TO. Bản đó đặt mặc
    /// định `putty_log_large.txt` để hai Agent trên CÙNG một máy không đọc chung một cái cân —
    /// nhưng ở máy trạm ngoài xưởng chỉ có MỘT PuTTY, ghi vào đường dẫn chuẩn cũ
    /// `D:\scale\putty_log.txt`. Không có bước lui này thì Agent cân to không đọc được gì, và
    /// vì trạm chỉ được tự đăng ký khi có số cân nên máy đó KHÔNG BAO GIỜ hiện ra trạm cân to
    /// (lỗi thật 2026-08-04). File riêng vẫn được ưu tiên tuyệt đối: máy nào đã mở PuTTY thứ hai
    /// thì bước lui không bao giờ chạy tới.
    /// </summary>
    public static string[] ResolveLogFilePaths(IConfiguration config)
    {
        string chinh = config.GetValue<string>("Scale:LogFilePath")
                       ?? config.GetValue<string>("Scale:SimulationFilePath")
                       ?? DefaultLogFilePath;

        string? duPhong = config.GetValue<string>("Scale:LogFilePathFallback");

        return new[] { chinh, duPhong ?? string.Empty }
            .Where(p => !string.IsNullOrWhiteSpace(p))
            .Select(p => p.Trim())
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToArray();
    }

    private void InitializeSerialPort()
    {
        try
        {
            string portName = _config.GetValue<string>("Scale:PortName", "COM1") ?? "COM1";
            int baudRate = _config.GetValue<int>("Scale:BaudRate", 9600);
            int dataBits = _config.GetValue<int>("Scale:DataBits", 8);
            Parity parity = _config.GetValue<Parity>("Scale:Parity", Parity.None);
            StopBits stopBits = _config.GetValue<StopBits>("Scale:StopBits", StopBits.One);

            _serialPort = new SerialPort(portName, baudRate, parity, dataBits, stopBits);
            _serialPort.DataReceived += OnDataReceived;
            _serialPort.Open();

            _logger.LogInformation("Serial Port {Port} opened successfully (Baud: {Baud})", portName, baudRate);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to initialize serial port. Falling back to simulation mode.");
        }
    }

    private void OnDataReceived(object sender, SerialDataReceivedEventArgs e)
    {
        if (_serialPort == null || !_serialPort.IsOpen) return;

        try
        {
            string rawData = _serialPort.ReadExisting();
            IngestSerialData(rawData);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error reading data from serial port.");
        }
    }

    /// <summary>
    /// Giữ chữ ký cũ (double) để không phá vỡ chỗ gọi khác nếu có — dùng nội bộ, không nên
    /// dùng cho luồng gửi backend nữa (mất thông tin IsStable). Ưu tiên gọi
    /// <see cref="ReadCurrentWeightWithStability"/>.
    /// </summary>
    public double ReadCurrentWeight() => ReadCurrentWeightWithStability().Weight ?? _lastGoodWeight ?? 0.0;

    // TV6 (p0-c-scale-algorithm.md Mục A.10): VBA giữ NGUYÊN giá trị hiển thị cũ khi đọc lỗi/rác
    // (ReadLastLineFast trả "" khi file không tồn tại, ExtractLastNumber trả "" khi không có số
    // hợp lệ -> PushRawToForm Exit Sub, không ghi đè). Trước bản vá này .NET trả thẳng 0.0 khi
    // lỗi — một giá trị SỐ HỢP LỆ dễ bị hiểu nhầm là "cân đang đọc 0kg" thay vì "mất kết nối/dữ
    // liệu rác". Giữ _lastGoodWeight làm baseline "giá trị hiển thị cũ" tương đương VBA.
    private double? _lastGoodWeight;

    /// <summary>
    /// PB-2: điểm đọc chính thức có kèm cờ ổn định — Worker.cs dùng hàm này thay vì
    /// ReadCurrentWeight() để gửi is_stable thật lên backend thay vì hard-code true.
    /// Weight=null nghĩa là "không đọc được số hợp lệ lần này" (TV6) — khác với weight=0.0
    /// nghĩa là "cân thật sự đọc đúng 0kg". Tầng gọi (Worker.cs) tự quyết định giữ giá trị cũ.
    /// </summary>
    public (double? Weight, bool IsStable) ReadCurrentWeightWithStability()
    {
        if (_useSimulation || _serialPort == null || !_serialPort.IsOpen)
        {
            return ReadSimulatedWeight();
        }

        // Cổng COM đọc theo sự kiện (OnDataReceived chạy trên thread riêng của SerialPort),
        // không đọc đồng bộ được trong vòng lặp Worker — nên trả về số đọc mới nhất mà
        // IngestSerialData() đã chốt. Trước bản vá này chỗ này trả thẳng (null, false), mà
        // Worker chỉ push lên backend khi Weight.HasValue → cân thật cắm qua RS232 KHÔNG BAO
        // GIỜ đẩy được số nào lên (lỗi bị che khuất suốt vì cả 3 file appsettings đóng gói
        // trong MSI đều để Scale:UseSimulation=true, luôn rơi vào nhánh đọc file PuTTY).
        lock (_serialLock)
        {
            return (_latestSerialWeight, _latestSerialStable);
        }
    }

    // Số đọc mới nhất chốt từ cổng COM. Không đặt thời hạn hết hiệu lực: giữ giá trị cuối cho
    // tới khi có số mới — đúng quy ước TV6 và khớp hành vi nhánh đọc file (dòng cuối của log
    // PuTTY cũng nằm nguyên đó tới khi cân ghi dòng mới). Cache backend TTL 15s vẫn là lớp
    // chặn cuối nếu Agent chết hẳn.
    private readonly object _serialLock = new();
    private double? _latestSerialWeight;
    private bool _latestSerialStable;

    // Đệm dòng dở dang giữa 2 lần OnDataReceived. SerialPort.ReadExisting() trả về đúng những
    // gì đang có trong buffer tại thời điểm đó, hoàn toàn có thể CẮT GIỮA một dòng (vd
    // "12,ST,GS,+00001" | "0.5g\r\n"). Nếu đưa thẳng mảnh cụt vào CleanWeight thì token số
    // cuối cùng là số cụt ("00001" thay vì 10.5) — sai số cân mà không có dấu hiệu gì. Vì vậy
    // chỉ xử lý các dòng ĐÃ KẾT THÚC bằng CR/LF, phần đuôi dở giữ lại chờ chunk kế tiếp.
    private readonly System.Text.StringBuilder _serialBuffer = new();

    /// <summary>
    /// Số đọc mới nhất đã chốt từ cổng COM — dùng cho unit test và chẩn đoán tại chỗ; luồng
    /// chạy thật đi qua <see cref="ReadCurrentWeightWithStability"/>.
    /// </summary>
    public (double? Weight, bool IsStable) LatestSerialReading
    {
        get { lock (_serialLock) { return (_latestSerialWeight, _latestSerialStable); } }
    }

    /// <summary>
    /// Tách dữ liệu thô từ cổng COM thành từng dòng trọn vẹn rồi chốt số đọc mới nhất.
    /// Tách riêng khỏi OnDataReceived để unit test được mà không cần cổng COM thật.
    /// </summary>
    public void IngestSerialData(string chunk)
    {
        if (string.IsNullOrEmpty(chunk)) return;

        var completedLines = new List<string>();

        lock (_serialLock)
        {
            _serialBuffer.Append(chunk);
            string buffered = _serialBuffer.ToString();

            int lastBreak = buffered.LastIndexOfAny(new[] { '\r', '\n' });
            if (lastBreak < 0) return; // chưa có dòng nào trọn vẹn — chờ chunk kế tiếp

            string complete = buffered.Substring(0, lastBreak + 1);
            _serialBuffer.Clear();
            _serialBuffer.Append(buffered.Substring(lastBreak + 1));

            foreach (string line in complete.Split('\r', '\n'))
            {
                if (LaDongSoCan(line)) completedLines.Add(line);
            }
        }

        // Chạy StableFilter NGOÀI lock: mỗi dòng đọc được là 1 "lần đọc" theo đúng nghĩa của
        // VBA Mod_delta_raw.StableFilter (2 lần liên tiếp cùng chuỗi thô = ổn định), không
        // phải mỗi vòng poll của Worker.
        foreach (string line in completedLines)
        {
            var (weight, isStable) = ReadWeightWithStability(line);
            if (!weight.HasValue) continue; // dòng rác: giữ nguyên số cũ, đúng TV6

            _lastGoodWeight = weight;
            lock (_serialLock)
            {
                _latestSerialWeight = weight;
                _latestSerialStable = isStable;
            }
        }
    }

    // Throttle cảnh báo "chưa thấy file log cân" — vòng lặp Worker nay chạy mỗi ReadIntervalMs
    // (mặc định 10ms), log mỗi vòng sẽ ghi 100 dòng/giây và làm ngập file log.
    private DateTime _nextMissingFileWarnAt = DateTime.MinValue;
    private DateTime _nextFallbackWarnAt = DateTime.MinValue;
    private static readonly TimeSpan MissingFileWarnInterval = TimeSpan.FromSeconds(30);

    /// <summary>
    /// File log đang dùng: ứng viên ĐẦU TIÊN thực sự tồn tại, hoặc null nếu không có cái nào.
    ///
    /// Tra lại mỗi lần đọc (không chốt cứng lúc khởi động) để PuTTY bật SAU Agent vẫn được nhận
    /// ra ngay, và để máy mở thêm PuTTY riêng cho cân to tự chuyển về file riêng mà không phải
    /// khởi động lại service.
    /// </summary>
    private string? ResolveActiveLogFile()
    {
        foreach (string path in _logFilePaths)
        {
            if (File.Exists(path)) return path;
        }

        return null;
    }

    private (double? Weight, bool IsStable) ReadSimulatedWeight()
    {
        try
        {
            // Trước đây nếu không thấy file thì tự tạo file giả với số cân bịa (12.45kg) —
            // khiến màn hình Trạm cân tưởng cân đang hoạt động dù PuTTY chưa bật/chưa log
            // đúng đường dẫn. Nay coi "không thấy file" là CHƯA NHẬN được tín hiệu cân thật
            // (weight=null, đúng quy ước TV6 — xem CleanWeight/ReadCurrentWeightWithStability)
            // — không tự bịa số, không tự tạo file.
            string? activePath = ResolveActiveLogFile();
            if (activePath is null)
            {
                if (DateTime.UtcNow >= _nextMissingFileWarnAt)
                {
                    _logger.LogWarning(
                        "Chưa nhận tín hiệu cân — không tìm thấy file log nào trong: {Paths}. Kiểm tra PuTTY đã bật Session Logging đúng một trong các đường dẫn này chưa.",
                        string.Join(" , ", _logFilePaths));
                    _nextMissingFileWarnAt = DateTime.UtcNow.Add(MissingFileWarnInterval);
                }
                return (null, false);
            }

            // Đang phải lui về file dự phòng — nói to ra, vì trên máy cài CẢ HAI bộ Agent thì
            // file dự phòng chính là log của CÂN NHỎ: số của cân nhỏ sẽ chạy trên màn hình cân
            // to mà không có dấu hiệu nào khác. Mở PuTTY riêng cho cân to là hết cảnh báo này.
            if (!string.Equals(activePath, _logFilePaths[0], StringComparison.OrdinalIgnoreCase)
                && DateTime.UtcNow >= _nextFallbackWarnAt)
            {
                _logger.LogWarning(
                    "Không thấy file log riêng {Chinh} nên đang đọc file DỰ PHÒNG {DuPhong}. Nếu máy này chạy cả Agent cân nhỏ thì hai bên đang đọc CHUNG một cái cân — hãy mở PuTTY riêng ghi vào file log riêng đó.",
                    _logFilePaths[0], activePath);
                _nextFallbackWarnAt = DateTime.UtcNow.Add(MissingFileWarnInterval);
            }

            string lastLine = ReadLastCompleteLine(activePath);
            if (lastLine.Length > 0)
            {
                var (weight, isStable) = ReadWeightWithStability(lastLine);
                if (weight.HasValue)
                {
                    _lastGoodWeight = weight;
                }
                return (weight, isStable);
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to read simulated weight: {Msg}", ex.Message);
        }
        return (null, false);
    }

    // Cửa sổ đọc đuôi file. Dòng dữ liệu cân dài vài chục ký tự nên 4KB thừa sức chứa nhiều
    // dòng cuối; không bao giờ cần đọc quá số này dù file log đã phình tới hàng chục MB.
    private const int TailWindowBytes = 4096;

    private static readonly char[] LineBreakChars = { '\r', '\n' };

    /// <summary>
    /// Dòng này có phải một khung số cân hay không — dùng để BỎ QUA dòng nhiễu khi chọn dòng cuối.
    ///
    /// ===== VÌ SAO CẦN (log cân to, máy trạm, 09/08/2026) =====
    /// Cái cân to phát XEN KẼ hai dòng: một dòng số thật rồi một dòng toàn số 0.
    ///
    ///     US,+000466.6  g
    ///     0000000
    ///     US,+000486.7  g
    ///     0000000
    ///
    /// "Dòng cuối cùng không rỗng" vì thế cứ một nhịp trúng số thật, một nhịp trúng "0000000" —
    /// mà "0000000" lại parse ra 0.0 hoàn toàn hợp lệ. Kết quả: số cân trên màn hình NHẤP NHÁY
    /// đúng-rồi-0, và tệ hơn là `StableFilter` không bao giờ thấy hai lần đọc liên tiếp giống
    /// nhau nên NEXT không chốt nổi bì (người dùng báo: "lúc ấn next thì lúc được, lúc lấy được
    /// là 00").
    ///
    /// ===== VÌ SAO NHẬN DẠNG KIỂU NÀY =====
    /// Điều kiện đặt HẸP nhất có thể: chỉ loại dòng TOÀN CHỮ SỐ. Một khung cân thật luôn có ít
    /// nhất một ký tự không phải chữ số — dấu phân cách (","), dấu (+/-), dấu thập phân, hoặc
    /// chữ (ST/US, "g"/"kg"). Cả hai định dạng đang gặp ở xưởng đều thoả:
    ///
    ///     US,+000466.6  g          (cân to)
    ///     12,ST,GS,+000010.5g      (cân nhỏ)
    ///
    /// Cố ý KHÔNG đòi phải có dấu "," hay dấu +/- — đòi chặt hơn thì gặp một con cân xuất định
    /// dạng khác là màn hình chết câm hoàn toàn, tệ hơn hẳn so với nhiễu.
    /// </summary>
    public static bool LaDongSoCan(string line)
    {
        string t = line.Trim();
        if (t.Length == 0) return false;

        foreach (char c in t)
        {
            if (c < '0' || c > '9') return true;
        }

        return false;
    }

    /// <summary>
    /// Lấy dòng cuối cùng KHÔNG RỖNG và ĐÃ KẾT THÚC của file log PuTTY.
    ///
    /// Thay cho <c>File.ReadAllLines</c> trước đây (đọc TOÀN BỘ file rồi lấy phần tử cuối). Ở
    /// nhịp 500ms cách cũ còn chịu được, nhưng nhịp đọc nay là 10ms (bám đúng VBA
    /// <c>StartFastLoop</c>) trong khi file log PuTTY phình dần suốt ca — đọc cả file 100
    /// lần/giây sẽ làm nghẹt I/O máy trạm. Ở đây seek thẳng tới cuối file, chỉ đọc
    /// <see cref="TailWindowBytes"/> byte cuối, nên chi phí không phụ thuộc kích thước file.
    ///
    /// BỎ QUA phần đuôi chưa có ký tự xuống dòng: ở 10ms, xác suất chộp đúng lúc PuTTY mới ghi
    /// được nửa dòng ("12,ST,GS,+0000") cao gấp ~50 lần so với 500ms, mà CleanWeight sẽ parse
    /// mảnh cụt đó thành một con số HỢP LỆ nhưng SAI. Đánh đổi: chậm hơn đúng một dòng (cân
    /// thường phát ~5-10 dòng/giây) để không bao giờ đọc phải số cụt.
    ///
    /// Cũng vá luôn khác biệt A.1 với VBA (p0-c-scale-algorithm.md): VBA
    /// <c>ReadLastLineFast</c> bỏ qua dòng rỗng (<c>If Len(s) &gt; 0</c>), bản .NET cũ lấy dòng
    /// vật lý cuối bất kể rỗng hay không.
    ///
    /// FileShare.ReadWrite|Delete là bắt buộc: PuTTY đang giữ file mở để ghi.
    /// </summary>
    public static string ReadLastCompleteLine(string path)
    {
        using var fs = new FileStream(
            path, FileMode.Open, FileAccess.Read,
            FileShare.ReadWrite | FileShare.Delete);

        long len = fs.Length;
        if (len == 0) return string.Empty;

        int window = (int) Math.Min(TailWindowBytes, len);
        fs.Seek(len - window, SeekOrigin.Begin);

        var buf = new byte[window];
        int read = fs.Read(buf, 0, window);
        if (read <= 0) return string.Empty;

        // ASCII: dữ liệu cân chỉ có số/dấu/chữ cái ASCII. Dùng ASCII tránh việc cửa sổ cắt
        // giữa một ký tự nhiều byte làm hỏng cả chuỗi giải mã.
        string text = System.Text.Encoding.ASCII.GetString(buf, 0, read);

        int lastBreak = text.LastIndexOfAny(LineBreakChars);
        if (lastBreak < 0) return string.Empty; // chưa có dòng nào kết thúc trong cửa sổ

        string[] lines = text.Substring(0, lastBreak).Split(LineBreakChars, StringSplitOptions.RemoveEmptyEntries);
        for (int i = lines.Length - 1; i >= 0; i--)
        {
            string t = lines[i].Trim();
            if (LaDongSoCan(t)) return t;
        }

        return string.Empty;
    }

    // Trạng thái StableFilter (tương đương biến Static trong VBA Mod_delta_raw.StableFilter) —
    // instance field vì mỗi ScaleReader phục vụ đúng 1 workstation/cân (DI scoped), không dùng
    // static class-level field (sẽ lẫn trạng thái nếu sau này 1 process phục vụ nhiều cân).
    private string? _lastRawValue;
    private string? _lastStableValue;
    private int _sameValueCount;

    /// <summary>
    /// PB-1 (đã sửa 2026-07-17): Trước đây dùng Regex.Match lấy SỐ ĐẦU TIÊN khớp trong chuỗi
    /// thô — ngược với VBA gốc (Modcleanweight.ExtractLastNumber) vốn Split(",") rồi duyệt
    /// TỪ CUỐI mảng về đầu, trả về token số hợp lệ cuối cùng. Xem
    /// .claude/p0-analysis/p0-c-scale-algorithm.md Mục A.2, Test vector TV1. Hàm này giờ
    /// implement đúng ExtractLastNumber, không phải regex-first-match.
    /// </summary>
    /// <summary>
    /// TV6: trả null (không phải 0.0) khi không trích được số hợp lệ nào — 0.0 là 1 kết quả cân
    /// HỢP LỆ (cân đang thật sự rỗng), phải phân biệt được với "đọc lỗi/dữ liệu rác".
    /// </summary>
    public double? CleanWeight(string rawInput) => TrichSoCan(rawInput).Value;

    /// <summary>
    /// Trích số cân, trả về CẢ giá trị LẪN chuỗi token đã tách được.
    ///
    /// Cần token vì `StableFilter` của VBA so trên chính token đó, không phải trên dòng thô —
    /// xem ghi chú ở <see cref="ReadWeightWithStability"/>.
    /// </summary>
    private static (double? Value, string Token) TrichSoCan(string rawInput)
    {
        if (string.IsNullOrEmpty(rawInput)) return (null, string.Empty);

        // VBA lọc whitelist [0-9+\-.,] TRƯỚC khi tách token (Mod_delta_raw.CleanScaleRaw /
        // ModRead_putty_log.CleanWeight) — nếu bỏ bước này, token cuối còn dính hậu tố đơn vị
        // (vd "+000010.5g") sẽ parse thất bại và rơi về token rác phía trước (mã trạm/timestamp).
        // Xem p0-c-scale-algorithm.md Mục A.3, TV1.
        var filtered = new System.Text.StringBuilder();
        foreach (char c in rawInput)
        {
            if ((c >= '0' && c <= '9') || c == '+' || c == '-' || c == '.' || c == ',')
            {
                filtered.Append(c);
            }
        }

        string[] tokens = filtered.ToString().Split(',');
        for (int i = tokens.Length - 1; i >= 0; i--)
        {
            string t = tokens[i].Trim();
            // VBA IsNumeric chấp nhận cả số nguyên/thập phân có dấu +/- ở đầu; dùng
            // NumberStyles tương đương để không lệch hành vi khi token có dấu "+".
            if (double.TryParse(t, System.Globalization.NumberStyles.Float, System.Globalization.CultureInfo.InvariantCulture, out double val))
            {
                return (val, t);
            }
        }
        return (null, string.Empty);
    }

    /// <summary>
    /// PB-2 (đã sửa 2026-07-17): Cổng vào cho luồng đọc cân — làm sạch raw string rồi áp
    /// StableFilter, trả về cả giá trị VÀ cờ ổn định để tầng gọi (Worker/API) không còn phải
    /// hard-code stable=true. Trước đây .NET hoàn toàn không có StableFilter ở bất kỳ tầng
    /// nào (p0-c-scale-algorithm.md Mục A.4) — WeighingStation.vue gửi thẳng stable:true.
    /// </summary>
    /// <remarks>
    /// ===== SỬA 09/08/2026: đưa TOKEN SỐ vào StableFilter, không phải cả dòng thô =====
    ///
    /// VBA `ModRead_putty_log` -> `Mod_delta_raw.PushRawToForm` làm đúng thứ tự này:
    ///
    ///     rawNum = CleanScaleRaw(s)
    ///     rawNum = ExtractLastNumber(rawNum)     ' <- chỉ còn CON SỐ
    ///     filtered = StableFilter(rawNum)        ' <- so trên CON SỐ
    ///
    /// Bản .NET trước đây đưa `rawInput` (nguyên dòng) vào StableFilter. Với cái cân to thì đó là
    /// sai nghiêm trọng, vì dòng của nó mang cả cờ trạng thái ST/US ở đầu và cờ này nhảy liên tục
    /// ngay cả khi con số đứng yên:
    ///
    ///     US,-008359.3  g
    ///     ST,-008359.3  g      <- cùng một số, nhưng CHUỖI khác -> bộ đếm bị reset
    ///
    /// Hậu quả: cân đứng yên rồi mà Agent vẫn báo "chưa ổn định", nên bấm NEXT không chốt được
    /// bì. So trên token thì hai dòng trên là một, đúng như VBA — và cũng đúng ý nghĩa: cái đang
    /// cần biết là SỐ có đứng yên không, chứ không phải cái nhãn trạng thái có đổi không.
    ///
    /// Cố ý KHÔNG đọc cờ ST/US để suy ra ổn định, dù cái cân có nói sẵn: VBA không dùng nó, và
    /// đổi định nghĩa "ổn định" là đổi luôn thời điểm chốt bì — phải là một quyết định riêng có
    /// người xác nhận, không phải hệ quả phụ của một lần vá lỗi.
    /// </remarks>
    public (double? Weight, bool IsStable) ReadWeightWithStability(string rawInput)
    {
        var (weight, token) = TrichSoCan(rawInput);
        bool isStable = weight.HasValue && StableFilter(token);
        return (weight, isStable);
    }

    /// <summary>
    /// Port đúng Mod_delta_raw.StableFilter (VBA):
    ///   Static lastVal, lastGood, cnt
    ///   If newVal = lastVal Then cnt += 1 Else cnt = 0
    ///   lastVal = newVal
    ///   If cnt >= 1 Then lastGood = newVal
    ///   Return lastGood
    /// "Ổn định" (trả về true) nghĩa là 2 lần đọc liên tiếp cho CÙNG một chuỗi thô (so sánh
    /// chuỗi tuyệt đối, không dung sai numeric — đúng hành vi VBA). Trạng thái tồn tại xuyên
    /// suốt các lần gọi trên cùng 1 instance ScaleReader (tương đương Static trong VBA module).
    /// </summary>
    public bool StableFilter(string newRawValue)
    {
        if (newRawValue == _lastRawValue)
        {
            _sameValueCount++;
        }
        else
        {
            _sameValueCount = 0;
        }

        _lastRawValue = newRawValue;

        if (_sameValueCount >= 1)
        {
            _lastStableValue = newRawValue;
            return true;
        }

        return false;
    }

    public void Dispose()
    {
        if (_serialPort != null)
        {
            if (_serialPort.IsOpen)
            {
                _serialPort.Close();
            }
            _serialPort.Dispose();
        }
    }
}
