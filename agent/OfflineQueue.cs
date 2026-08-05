using System;
using System.IO;
using Microsoft.Data.Sqlite;
using Microsoft.Extensions.Logging;

namespace DFAgent;

public class OfflineQueue
{
    private readonly string _dbPath;
    private readonly ILogger<OfflineQueue> _logger;

    public OfflineQueue(ILogger<OfflineQueue> logger)
    {
        _logger = logger;
        _dbPath = ResolveDbPath(logger);
        InitializeDatabase();
    }

    /// <summary>
    /// Chọn chỗ đặt agent_cache.db.
    ///
    /// KHÔNG còn đặt cạnh file .exe: từ bản 4.0 Agent CÂN TO chạy trong phiên đăng nhập của
    /// thợ (để mô phỏng chuột được — xem RackSender/ADR-012), mà tài khoản thường KHÔNG ghi
    /// được vào Program Files. Đặt trong LocalAppData của chính tài khoản đang chạy: chạy
    /// dưới service (LocalSystem) hay dưới tài khoản thợ đều ghi được, và hai chế độ không
    /// bao giờ chạy cùng lúc trên một bản cài nên không có chuyện hai kho lệch nhau.
    ///
    /// Vẫn lùi về cạnh .exe nếu vì lý do nào đó không tạo được thư mục — thà dùng chỗ cũ còn
    /// hơn mất hàng đợi offline.
    /// </summary>
    private static string ResolveDbPath(ILogger<OfflineQueue> logger)
    {
        string exeDir = AppDomain.CurrentDomain.BaseDirectory;
        string legacyPath = Path.Combine(exeDir, "agent_cache.db");

        try
        {
            string root = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            if (string.IsNullOrWhiteSpace(root)) return legacyPath;

            // Tên thư mục cài (DFAgent-Small / DFAgent-Large) — hai bản trên cùng một máy phải
            // có hai kho riêng, đúng như hồi mỗi bản một thư mục cài.
            string productDir = new DirectoryInfo(exeDir.TrimEnd(Path.DirectorySeparatorChar)).Name;
            string dir = Path.Combine(root, "DF Local Agent", productDir);
            Directory.CreateDirectory(dir);
            string path = Path.Combine(dir, "agent_cache.db");

            // Nâng cấp từ bản cũ: chuyển kho đang nằm cạnh .exe sang chỗ mới, không để lại
            // mẻ cân/lệnh in chưa đồng bộ ở file mà từ nay không ai đọc nữa.
            if (!File.Exists(path) && File.Exists(legacyPath))
            {
                File.Copy(legacyPath, path);
                logger.LogInformation("Đã chuyển agent_cache.db từ {Old} sang {New}.", legacyPath, path);
            }

            return path;
        }
        catch (Exception ex)
        {
            logger.LogWarning("Không dùng được LocalAppData cho agent_cache.db ({Msg}) — dùng lại thư mục cài.", ex.Message);
            return legacyPath;
        }
    }

    private void InitializeDatabase()
    {
        try
        {
            using var connection = new SqliteConnection($"Data Source={_dbPath}");
            connection.Open();

            string createTables = @"
                CREATE TABLE IF NOT EXISTS scale_readings (
                    id TEXT PRIMARY KEY,
                    device_id TEXT,
                    raw_value TEXT,
                    normalized_value REAL,
                    timestamp TEXT,
                    synced INTEGER DEFAULT 0
                );
                CREATE TABLE IF NOT EXISTS print_jobs (
                    id TEXT PRIMARY KEY,
                    label_payload TEXT,
                    label_size TEXT,
                    created_at TEXT,
                    printed INTEGER DEFAULT 0
                );
            ";

            using var command = new SqliteCommand(createTables, connection);
            command.ExecuteNonQuery();
            _logger.LogInformation("SQLite cache database initialized successfully at: {Path}", _dbPath);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to initialize SQLite cache database.");
        }
    }

    public void SaveScaleReading(string deviceId, string rawValue, double normalizedValue)
    {
        try
        {
            using var connection = new SqliteConnection($"Data Source={_dbPath}");
            connection.Open();

            string insert = @"
                INSERT INTO scale_readings (id, device_id, raw_value, normalized_value, timestamp)
                VALUES ($id, $deviceId, $rawValue, $normalizedValue, $timestamp);
            ";

            using var command = new SqliteCommand(insert, connection);
            command.Parameters.AddWithValue("$id", Guid.NewGuid().ToString());
            command.Parameters.AddWithValue("$deviceId", deviceId);
            command.Parameters.AddWithValue("$rawValue", rawValue);
            command.Parameters.AddWithValue("$normalizedValue", normalizedValue);
            command.Parameters.AddWithValue("$timestamp", DateTime.UtcNow.ToString("o"));

            command.ExecuteNonQuery();
            _logger.LogInformation("Saved scale reading to offline cache: {Val} kg", normalizedValue);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to cache scale reading offline.");
        }
    }

    public void QueuePrintJob(string labelPayload, string labelSize)
    {
        try
        {
            using var connection = new SqliteConnection($"Data Source={_dbPath}");
            connection.Open();

            string insert = @"
                INSERT INTO print_jobs (id, label_payload, label_size, created_at)
                VALUES ($id, $payload, $size, $created);
            ";

            using var command = new SqliteCommand(insert, connection);
            command.Parameters.AddWithValue("$id", Guid.NewGuid().ToString());
            command.Parameters.AddWithValue("$payload", labelPayload);
            command.Parameters.AddWithValue("$size", labelSize);
            command.Parameters.AddWithValue("$created", DateTime.UtcNow.ToString("o"));

            command.ExecuteNonQuery();
            _logger.LogInformation("Queued print job offline.");
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Failed to queue print job offline.");
        }
    }
}
