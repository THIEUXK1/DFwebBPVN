<?php

namespace App\Services;

/**
 * PostgreSQL production_web chạy trên 1 server vật lý nhưng có 2 dải IP
 * (10.0.60.209 và 192.168.250.151) — dải nào ping/TCP-connect được thì dùng dải đó,
 * không hardcode 1 IP cố định trong .env. Kết quả được cache ra file ngắn hạn để không
 * phải TCP-test lại trên từng request.
 *
 * Dùng file I/O thuần thay vì Cache facade: config/database.php được nạp RẤT sớm
 * trong bootstrap (LoadConfiguration), trước khi facade root được gán (RegisterFacades
 * chạy sau) — gọi Cache::... ở đây sẽ ném "A facade root has not been set."
 */
class DbHostResolver
{
    private const TTL_SECONDS = 20;

    // 0.5s quá sát: đo thật 2026-08-02 thấy handshake tới 10.0.60.209 chỉ ~10ms và ổn định
    // 5/5 lần, nhưng chỉ cần MỘT lần mạng nhiễu vượt ngưỡng là cả hệ thống chết 20 giây
    // (xem ghi chú ở writeCache bên dưới). Nới lên 2s: không làm chậm đường bình thường
    // (probe thành công vẫn trả về sau ~10ms), chỉ nới trần cho trường hợp xấu.
    private const PROBE_TIMEOUT_SECONDS = 2.0;

    public static function resolve(): string
    {
        $configuredHost = (string) env('DB_HOST', '127.0.0.1');

        $candidates = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DB_HOST_CANDIDATES', $configuredHost))
        )));

        // Probe host trong DB_HOST TRƯỚC, rồi mới tới các ứng viên còn lại.
        //
        // Ghi chú 2026-08-02 ở trên giả định probe TRƯỢT thì trả lời ngay ("connection
        // refused"). Đo lại 2026-08-05 trên máy dev: KHÔNG đúng — `fsockopen` tới
        // 127.0.0.1:5433 (không có Postgres cục bộ) trả errno **10060 = hết giờ**, không
        // phải 10061 = bị từ chối, tức gói tin bị firewall NUỐT chứ không bị đá về. Mỗi lần
        // probe trượt vì vậy đốt trọn 2 giây. Với thứ tự cũ (127.0.0.1 đứng đầu danh sách),
        // cứ mỗi 20 giây (TTL cache) lại có 1 request đứng hình 2 giây — và vì backend chạy
        // `php artisan serve` ĐƠN LUỒNG, mọi request khác của mọi tab đang mở xếp hàng chờ
        // theo. Đây là nguyên nhân "trang nào cũng chậm" trên máy dev.
        //
        // Đảo thứ tự sửa được cho CẢ HAI nơi mà không cần biết máy đang chạy là máy nào:
        // DB_HOST luôn là host chủ đích của chính môi trường đó, nên phát đầu tiên gần như
        // luôn trúng (~50ms). Danh sách ứng viên giữ nguyên vai trò dự phòng khi host chủ
        // đích thật sự chết.
        if ($configuredHost !== '') {
            array_unshift($candidates, $configuredHost);
            $candidates = array_values(array_unique($candidates));
        }

        if (count($candidates) <= 1) {
            return $candidates[0] ?? $configuredHost;
        }

        $port = (int) env('DB_PORT', 5432);
        $cacheFile = sys_get_temp_dir().'/df_pgsql_active_host.json';

        $cached = self::readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        foreach ($candidates as $host) {
            $conn = @fsockopen($host, $port, $errno, $errstr, self::PROBE_TIMEOUT_SECONDS);
            if ($conn) {
                fclose($conn);
                // CHỈ ghi cache khi thật sự nối được. Trước đây kết quả fallback cũng bị ghi
                // cache: một lần probe trượt là khoá cứng cả hệ thống vào host sai suốt 20
                // giây, mọi request trả 500 "connection refused" dù DB vẫn chạy bình thường —
                // lỗi thật gặp 2026-08-01 12:13 (log: mọi request nối 127.0.0.1:5433).
                self::writeCache($cacheFile, $host);

                return $host;
            }
        }

        // Không probe được cái nào: trả về host cấu hình chủ đích trong DB_HOST, KHÔNG phải
        // $candidates[0] — phần tử đầu danh sách thường là 127.0.0.1 (máy dev), nơi chắc chắn
        // không có DB ở môi trường chạy thật. Không cache để lần sau còn probe lại ngay.
        return $configuredHost;
    }

    private static function readCache(string $file): ?string
    {
        if (!is_file($file) || (time() - filemtime($file)) > self::TTL_SECONDS) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return $data['host'] ?? null;
    }

    private static function writeCache(string $file, string $host): void
    {
        // Best-effort — nếu ghi lỗi (quyền, đĩa đầy) thì lần request sau tự TCP-test lại,
        // không ảnh hưởng tới việc resolve host cho request hiện tại.
        @file_put_contents($file, json_encode(['host' => $host]));
    }
}
