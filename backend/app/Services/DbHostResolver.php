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

    public static function resolve(): string
    {
        $candidates = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('DB_HOST_CANDIDATES', env('DB_HOST', '127.0.0.1')))
        )));

        if (count($candidates) <= 1) {
            return $candidates[0] ?? env('DB_HOST', '127.0.0.1');
        }

        $port = (int) env('DB_PORT', 5432);
        $cacheFile = sys_get_temp_dir().'/df_pgsql_active_host.json';

        $cached = self::readCache($cacheFile);
        if ($cached !== null) {
            return $cached;
        }

        $resolved = $candidates[0];
        foreach ($candidates as $host) {
            $conn = @fsockopen($host, $port, $errno, $errstr, 0.5);
            if ($conn) {
                fclose($conn);
                $resolved = $host;
                break;
            }
        }

        self::writeCache($cacheFile, $resolved);

        return $resolved;
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
