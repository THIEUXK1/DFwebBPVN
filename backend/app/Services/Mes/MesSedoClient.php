<?php
// backend/app/Services/Mes/MesSedoClient.php
//
// Client CHỈ ĐỌC tới VN-MES module Sedo Planboard. Dùng để lấy màu hiển thị thật của
// từng mã màu (BPDB không có dữ liệu màu — xem migration create_color_swatches_table).
//
// Luồng đúng của MES (đọc từ chính JS của trang /mes/databoardSedo/gantt.html):
//   1. POST {base}/sys/ssologin   (form-urlencoded: username, password) -> {"code":0}
//   2. POST {base}/rsedo/tBatch/getDataForGantt  (JSON body {}) -> {"code":0,"rows":[...]}
// Bước 2 BẮT BUỘC dùng lại cookie phiên của bước 1, nếu không MES trả về nguyên trang
// HTML đăng nhập kèm HTTP 200 (không phải 401) — vì vậy phải tự kiểm tra nội dung trả
// về có đúng JSON không, không thể tin mỗi status code.

namespace App\Services\Mes;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MesSedoClient
{
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Trả về danh sách bản ghi phẳng: mỗi phần tử là 1 dòng máy hoặc 1 mẻ con
     * (MES lồng mẻ trong tBatchEntityList của từng máy).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchGanttRows(): array
    {
        $base = rtrim((string) $this->config['base_url'], '/');
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;

        if (!$username || !$password) {
            throw new RuntimeException('Chưa cấu hình MES_USERNAME/MES_PASSWORD trong .env.');
        }

        $jar = new CookieJar();

        $login = Http::withOptions([
            'cookies' => $jar,
            'verify' => (bool) ($this->config['verify_ssl'] ?? true),
        ])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            ->asForm()
            ->post($base . '/sys/ssologin', [
                'username' => $username,
                'password' => $password,
            ]);

        $loginJson = $login->json();
        if (!is_array($loginJson) || ($loginJson['code'] ?? null) !== 0) {
            // Không log $password, và cũng không log nguyên body (có thể chứa token phiên).
            throw new RuntimeException(
                'Đăng nhập MES thất bại: ' . ($loginJson['msg'] ?? 'phản hồi không hợp lệ')
            );
        }

        $response = Http::withOptions([
            'cookies' => $jar,
            'verify' => (bool) ($this->config['verify_ssl'] ?? true),
        ])
            ->timeout((int) ($this->config['timeout'] ?? 120))
            // PHẢI gửi đúng object rỗng "{}". Nếu dùng ->post($url, []) thì Laravel
            // json_encode mảng rỗng thành "[]" (mảng, không phải object) và MES deserialize
            // thất bại, trả {"code":500,"msg":"未知异常..."} — mất 1 lượt debug vì lỗi này
            // không nói gì về nguyên nhân thật.
            ->withBody('{}', 'application/json')
            ->post($base . '/rsedo/tBatch/getDataForGantt');

        $data = $response->json();

        if (!is_array($data)) {
            // Phiên hết hạn -> MES trả nguyên trang HTML đăng nhập kèm HTTP 200.
            throw new RuntimeException('MES không trả về JSON (nhiều khả năng phiên đăng nhập bị từ chối).');
        }

        if (!isset($data['rows'])) {
            throw new RuntimeException(sprintf(
                'MES báo lỗi khi lấy dữ liệu Gantt (code=%s): %s',
                $data['code'] ?? '?',
                $data['msg'] ?? 'không rõ nguyên nhân'
            ));
        }

        return $this->flatten($data['rows']);
    }

    /**
     * MES đóng gói màu theo BGR (chuẩn Windows COLORREF), KHÔNG phải RGB thường:
     * byte thấp nhất là ĐỎ. Công thức lấy nguyên từ getRGBByNum() trong
     * ganttDrawerSVG.js của MES để không lệch với màu người dùng thấy bên đó.
     */
    public static function bgrToHex(int $value): string
    {
        $r = $value & 0xFF;
        $g = ($value >> 8) & 0xFF;
        $b = ($value >> 16) & 0xFF;

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    /** @return array<int, array<string, mixed>> */
    private function flatten(array $rows): array
    {
        $flat = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $children = $row['tBatchEntityList'] ?? [];
            unset($row['tBatchEntityList']);
            $flat[] = $row;

            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        unset($child['tBatchEntityList']);
                        $flat[] = $child;
                    }
                }
            }
        }

        return $flat;
    }
}
