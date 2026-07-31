using System;
using System.IO;
using System.IO.Ports;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class ScaleReader : IDisposable
{
    private readonly ILogger<ScaleReader> _logger;
    private readonly IConfiguration _config;
    private SerialPort? _serialPort;
    private readonly string _simulationFilePath;
    private readonly bool _useSimulation;

    public ScaleReader(ILogger<ScaleReader> logger, IConfiguration config)
    {
        _logger = logger;
        _config = config;
        _useSimulation = _config.GetValue<bool>("Scale:UseSimulation", true);
        _simulationFilePath = _config.GetValue<string>("Scale:SimulationFilePath", "D:\\SCALE\\putty_log.txt");

        if (!_useSimulation)
        {
            InitializeSerialPort();
        }
        else
        {
            _logger.LogInformation("Scale Reader is running in SIMULATION mode reading file: {Path}", _simulationFilePath);
        }
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
                if (!string.IsNullOrWhiteSpace(line)) completedLines.Add(line);
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

    // Throttle cảnh báo "chưa thấy file log cân" — vòng lặp Worker chạy mỗi
    // PollIntervalMs (mặc định 500ms), log mỗi vòng sẽ spam log file vô ích.
    private DateTime _nextMissingFileWarnAt = DateTime.MinValue;
    private static readonly TimeSpan MissingFileWarnInterval = TimeSpan.FromSeconds(30);

    private (double? Weight, bool IsStable) ReadSimulatedWeight()
    {
        try
        {
            // Trước đây nếu không thấy file thì tự tạo file giả với số cân bịa (12.45kg) —
            // khiến màn hình Trạm cân tưởng cân đang hoạt động dù PuTTY chưa bật/chưa log
            // đúng đường dẫn. Nay coi "không thấy file" là CHƯA NHẬN được tín hiệu cân thật
            // (weight=null, đúng quy ước TV6 — xem CleanWeight/ReadCurrentWeightWithStability)
            // — không tự bịa số, không tự tạo file.
            if (!File.Exists(_simulationFilePath))
            {
                if (DateTime.UtcNow >= _nextMissingFileWarnAt)
                {
                    _logger.LogWarning(
                        "Chưa nhận tín hiệu cân — không tìm thấy file log tại {Path}. Kiểm tra PuTTY đã bật Session Logging đúng đường dẫn này chưa.",
                        _simulationFilePath);
                    _nextMissingFileWarnAt = DateTime.UtcNow.Add(MissingFileWarnInterval);
                }
                return (null, false);
            }

            // Read the last line of the file (mimicking putty log tail) — giữ nguyên hành vi
            // "lấy dòng vật lý cuối" hiện có; VBA gốc còn bỏ qua dòng rỗng (ReadLastLineFast)
            // nhưng đó là khác biệt A.1 riêng, chưa sửa trong đợt này (không phải PB-1/PB-2).
            string[] lines = File.ReadAllLines(_simulationFilePath);
            if (lines.Length > 0)
            {
                string lastLine = lines[^1];
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
    public double? CleanWeight(string rawInput)
    {
        if (string.IsNullOrEmpty(rawInput)) return null;

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
                return val;
            }
        }
        return null;
    }

    /// <summary>
    /// PB-2 (đã sửa 2026-07-17): Cổng vào cho luồng đọc cân — làm sạch raw string rồi áp
    /// StableFilter, trả về cả giá trị VÀ cờ ổn định để tầng gọi (Worker/API) không còn phải
    /// hard-code stable=true. Trước đây .NET hoàn toàn không có StableFilter ở bất kỳ tầng
    /// nào (p0-c-scale-algorithm.md Mục A.4) — WeighingStation.vue gửi thẳng stable:true.
    /// </summary>
    public (double? Weight, bool IsStable) ReadWeightWithStability(string rawInput)
    {
        double? weight = CleanWeight(rawInput);
        bool isStable = weight.HasValue && StableFilter(rawInput);
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
