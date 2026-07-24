using System;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class Worker : BackgroundService
{
    private readonly ILogger<Worker> _logger;
    private readonly IConfiguration _config;
    private readonly ScaleReader _scaleReader;
    private readonly LabelPrinter _labelPrinter;
    private readonly OfflineQueue _offlineQueue;
    private readonly PrinterDiscovery _printerDiscovery;
    private readonly HttpClient _httpClient;
    private readonly string _backendUrl;
    private readonly string _workstationId;
    private readonly int _pollIntervalMs;

    // Danh sách máy in cài sẵn hiếm khi đổi — không cần báo cáo mỗi vòng lặp 500ms
    // như số cân. Báo lại mỗi 60 giây (đủ nhanh để phát hiện máy in mới cắm vào).
    private DateTime _nextPrinterReportAt = DateTime.MinValue;
    private static readonly TimeSpan PrinterReportInterval = TimeSpan.FromSeconds(60);

    public Worker(
        ILogger<Worker> logger,
        IConfiguration config,
        ScaleReader scaleReader,
        LabelPrinter labelPrinter,
        OfflineQueue offlineQueue,
        PrinterDiscovery printerDiscovery)
    {
        _logger = logger;
        _config = config;
        _scaleReader = scaleReader;
        _labelPrinter = labelPrinter;
        _offlineQueue = offlineQueue;
        _printerDiscovery = printerDiscovery;

        _backendUrl = _config.GetValue<string>("Backend:Url", "http://localhost:8500/api") ?? "http://localhost:8500/api";
        _workstationId = _config.GetValue<string>("Workstation:Id", "WS-01") ?? "WS-01";
        _pollIntervalMs = _config.GetValue<int>("Scale:PollIntervalMs", 500);

        _httpClient = new HttpClient { Timeout = TimeSpan.FromSeconds(5) };

        // Xác thực Agent bằng token workstation (backend middleware AgentAuth) — cùng
        // token đã cấp cho handshake trình duyệt kiosk tại đúng máy này, KHÔNG phải
        // tài khoản người dùng. Xem local-agent-architecture.md Mục 4.2.
        string? workstationToken = _config.GetValue<string>("Workstation:Token");
        if (!string.IsNullOrEmpty(workstationToken))
        {
            _httpClient.DefaultRequestHeaders.Add("X-Workstation-Token", workstationToken);
        }
        else
        {
            _logger.LogWarning("Workstation:Token chưa được cấu hình — backend sẽ từ chối mọi request nếu bật enforcement (agent.auth).");
        }
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        _logger.LogInformation("DF Local Agent started for Workstation: {WS}", _workstationId);

        double lastLoggedWeight = 0.0;

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                // 1. Read scale weight (kèm cờ ổn định thật, thay cho hard-code stable=true
                // trước đây ở tầng frontend — PB-2, xem ScaleReader.StableFilter)
                var (currentWeight, isStable) = _scaleReader.ReadCurrentWeightWithStability();

                // TV6 (p0-c-scale-algorithm.md Mục A.10): currentWeight=null nghĩa là lỗi đọc/dữ
                // liệu rác — KHÔNG push lên backend để tránh ghi đè cache đang giữ giá trị hợp lệ
                // gần nhất bằng "0.0 giả". Backend cache có TTL 15s tự đóng vai trò "giữ nguyên
                // giá trị hiển thị cũ" tương đương VBA (ReadLastLineFast/PushRawToForm im lặng bỏ
                // qua khi không có số hợp lệ), không cần Agent tự làm việc này.
                if (currentWeight.HasValue)
                {
                    if (Math.Abs(currentWeight.Value - lastLoggedWeight) > 0.05)
                    {
                        _logger.LogInformation("Scale Weight Changed: {W} kg (Stable: {Stable})", currentWeight.Value, isStable);
                        lastLoggedWeight = currentWeight.Value;
                    }

                    // 2. Push weight to API
                    await PushWeightToBackendAsync(currentWeight.Value, isStable);
                }
                else
                {
                    _logger.LogDebug("Scale read returned no valid data this cycle — keeping last known value (not pushed).");
                }

                // 3. Fetch and process pending print jobs
                await ProcessPendingPrintJobsAsync();

                // 4. Report installed printers to backend (throttled — xem PrinterReportInterval)
                if (DateTime.UtcNow >= _nextPrinterReportAt)
                {
                    await ReportInstalledPrintersAsync();
                    _nextPrinterReportAt = DateTime.UtcNow.Add(PrinterReportInterval);
                }
            }
            catch (Exception ex)
            {
                _logger.LogWarning("Loop execution warning: {Msg}", ex.Message);
            }

            await Task.Delay(_pollIntervalMs, stoppingToken);
        }
    }

    private async Task PushWeightToBackendAsync(double weight, bool isStable)
    {
        try
        {
            var payload = new
            {
                workstation_id = _workstationId,
                weight = weight,
                is_stable = isStable,
                timestamp = DateTime.UtcNow
            };

            HttpResponseMessage response = await _httpClient.PostAsJsonAsync($"{_backendUrl}/devices/readings", payload);
            if (response.IsSuccessStatusCode)
            {
                _logger.LogDebug("Weight reading pushed to backend: {W} kg (Stable: {Stable})", weight, isStable);
            }
            else
            {
                _logger.LogWarning("Backend rejected weight. Status: {Code}", response.StatusCode);
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Could not reach backend API. Caching scale reading locally. Error: {Msg}", ex.Message);
            _offlineQueue.SaveScaleReading(_workstationId, $"Simulated raw {weight} kg", weight);
        }
    }

    private async Task ProcessPendingPrintJobsAsync()
    {
        try
        {
            HttpResponseMessage response = await _httpClient.GetAsync($"{_backendUrl}/agents/{_workstationId}/jobs");
            if (response.IsSuccessStatusCode)
            {
                var jobs = await response.Content.ReadFromJsonAsync<PrintJobDto[]>();
                if (jobs != null && jobs.Length > 0)
                {
                    foreach (var job in jobs)
                    {
                        _logger.LogInformation("Processing print job: {JobId}", job.Id);
                        
                        string connType = job.PrinterConnectionType ?? "USB";
                        string address = job.PrinterAddress ?? "TSC TE200";

                        bool success = _labelPrinter.PrintLabel(job.LabelPayload, connType, address);
                        // Ack cả 2 chiều — trước đây chỉ ack khi in thành công, khiến lệnh in
                        // lỗi (kẹt giấy, máy in tắt...) đứng mãi ở PENDING, được lấy lại và thử
                        // in lại vô hạn ở vòng lặp sau mà không có bất kỳ dấu vết PRINT_FAILED
                        // nào ghi lại — "C. Lịch sử in thực tế" không bao giờ thấy lỗi thật.
                        await AcknowledgePrintJobAsync(job.Id, success);
                    }
                }
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to poll print jobs: {Msg}", ex.Message);
        }
    }

    private async Task ReportInstalledPrintersAsync()
    {
        try
        {
            var (printers, defaultPrinter) = _printerDiscovery.ListInstalledPrinters();
            if (printers.Count == 0)
            {
                return;
            }

            var payload = new
            {
                workstation_id = _workstationId,
                printers = printers,
                default_printer = defaultPrinter,
            };

            HttpResponseMessage response = await _httpClient.PostAsJsonAsync($"{_backendUrl}/agents/{_workstationId}/printers", payload);
            if (response.IsSuccessStatusCode)
            {
                _logger.LogInformation("Reported {Count} installed printers (default: {Default})", printers.Count, defaultPrinter ?? "none");
            }
            else
            {
                _logger.LogWarning("Backend rejected printer report. Status: {Code}", response.StatusCode);
            }
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Could not report installed printers: {Msg}", ex.Message);
        }
    }

    private async Task AcknowledgePrintJobAsync(string jobId, bool success)
    {
        try
        {
            // Quy ước trạng thái dùng chung toàn hệ thống là PENDING/PRINTED/FAILED (badge
            // trạng thái frontend, print_attempts.status) — trước đây gửi "SUCCESS" không
            // khớp quy ước này.
            var payload = new { job_id = jobId, status = success ? "PRINTED" : "FAILED" };
            await _httpClient.PostAsJsonAsync($"{_backendUrl}/jobs/{jobId}/ack", payload);
            _logger.LogInformation("Acknowledged print job: {JobId} ({Status})", jobId, payload.status);
        }
        catch (Exception ex)
        {
            _logger.LogWarning("Failed to ack print job {JobId}: {Msg}", jobId, ex.Message);
        }
    }

    public class PrintJobDto
    {
        public string Id { get; set; } = string.Empty;
        public string LabelPayload { get; set; } = string.Empty;
        public string? PrinterConnectionType { get; set; }
        public string? PrinterAddress { get; set; }
    }
}
