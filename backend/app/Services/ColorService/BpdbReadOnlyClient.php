<?php
// backend/app/Services/ColorService/BpdbReadOnlyClient.php
//
// Client CHỈ ĐỌC duy nhất được phép giao tiếp với BPDB (Color Service, SQL Server thật
// tại nhà máy). Kết nối qua PDO_ODBC (không có driver sqlsrv/pdo_sqlsrv trên máy này).
//
// AN TOÀN: tài khoản `color` phía server thực ra có quyền sysadmin (phát hiện khi khảo
// sát 2026-07-20, xem colorservice-trace-report mục 09) — KHÔNG được tin vào quyền
// database để chặn ghi. Class này tự chặn ở tầng code: mọi câu lệnh phải bắt đầu bằng
// SELECT, không cho phép nhiều câu lệnh nối bằng ";" trong 1 lần gọi.

namespace App\Services\ColorService;

use PDO;
use PDOException;
use RuntimeException;

class BpdbReadOnlyClient
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $config)
    {
    }

    /**
     * Chạy 1 câu SELECT tham số hóa, trả về mảng kết quả (FETCH_ASSOC).
     *
     * @throws RuntimeException nếu $sql không phải SELECT đơn lẻ, hoặc lỗi kết nối.
     */
    public function select(string $sql, array $params = []): array
    {
        $this->assertReadOnlyStatement($sql);

        try {
            $stmt = $this->connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Không log nguyên $e->getMessage() nếu nó có thể lẫn thông tin kết nối —
            // trong thực tế PDO ODBC thường chỉ trả lỗi cú pháp/SQLSTATE, nhưng vẫn
            // tự strip phòng hờ chuỗi host/user xuất hiện.
            throw new RuntimeException(
                'BPDB query failed: ' . $this->sanitizeErrorMessage($e->getMessage())
            );
        }
    }

    /** Kiểm tra kết nối còn sống — dùng cho Admin dashboard (mục 12 tài liệu). */
    public function healthCheck(): array
    {
        $start = microtime(true);
        try {
            $this->select('SELECT 1 AS ok');
            return [
                'connected' => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (RuntimeException $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $driver = $this->config['odbc_driver'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        $encrypt = ($this->config['encrypt'] ?? false) ? 'yes' : 'no';
        $trustCert = ($this->config['trust_server_certificate'] ?? true) ? 'yes' : 'no';

        $dsn = "odbc:Driver={{$driver}};Server={$host},{$port};Database={$database};"
             . "Encrypt={$encrypt};TrustServerCertificate={$trustCert};";

        $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return $this->pdo;
    }

    private function assertReadOnlyStatement(string $sql): void
    {
        $trimmed = ltrim($sql);
        // Cho phép 1 câu SELECT duy nhất, không cho nối nhiều câu bằng ";" (chặn
        // "SELECT 1; DELETE ..." dù tài khoản có quyền ghi ở tầng server).
        if (!preg_match('/^SELECT\b/i', $trimmed)) {
            throw new RuntimeException('BpdbReadOnlyClient chỉ cho phép câu lệnh SELECT.');
        }
        $withoutTrailingSemicolon = rtrim(rtrim($trimmed), ';');
        if (str_contains($withoutTrailingSemicolon, ';')) {
            throw new RuntimeException('BpdbReadOnlyClient không cho phép nhiều câu lệnh trong 1 lần gọi.');
        }
    }

    private function sanitizeErrorMessage(string $message): string
    {
        $host = (string) ($this->config['host'] ?? '');
        if ($host !== '') {
            $message = str_replace($host, '[host]', $message);
        }
        return $message;
    }
}
