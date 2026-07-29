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
            ProcessRawData(rawData);
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
        return (null, false); // The serial port uses event-driven reading (OnDataReceived/ProcessRawData)
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

    private void ProcessRawData(string rawData)
    {
        var (cleanVal, isStable) = ReadWeightWithStability(rawData);
        _logger.LogDebug("Received raw serial input: {Raw} -> Cleaned: {Val} kg, Stable: {Stable}", rawData.Trim(), cleanVal, isStable);
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
