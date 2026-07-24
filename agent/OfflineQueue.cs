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
        _dbPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "agent_cache.db");
        InitializeDatabase();
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
