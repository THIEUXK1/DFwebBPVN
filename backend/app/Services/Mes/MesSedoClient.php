<?php
// backend/app/Services/Mes/MesSedoClient.php
//
// Client CHỈ ĐỌC tới VN-MES. Hai luồng dùng chung một phiên đăng nhập SSO:
//   1. fetchGanttRows()        -> /rsedo/tBatch/getDataForGantt  (màu hiển thị của mã màu)
//   2. fetchBatchCompletions() -> /eBatchLine/batchView          (giờ kết thúc nhuộm THẬT)
//
// Đăng nhập: POST {base}/sys/ssologin (form-urlencoded username/password) -> {"code":0}.
// Mọi request sau BẮT BUỘC dùng lại cookie phiên của bước đăng nhập; nếu không MES trả về
// nguyên trang HTML đăng nhập kèm HTTP 200 (không phải 401) — vì vậy phải tự kiểm tra nội
// dung trả về có đúng JSON không, không thể tin mỗi status code.

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
     * Trả về danh sách bản ghi phẳng cho tra màu: mỗi phần tử là 1 dòng máy hoặc 1 mẻ con
     * (MES lồng mẻ trong tBatchEntityList của từng máy).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchGanttRows(): array
    {
        $profile = $this->profile('default');
        $jar = new CookieJar();
        $this->login($jar, $profile);

        // PHẢI gửi đúng object rỗng "{}". Nếu dùng ->post($url, []) thì Laravel json_encode
        // mảng rỗng thành "[]" (mảng, không phải object) và MES deserialize thất bại, trả
        // {"code":500,...} — mất 1 lượt debug vì lỗi này không nói gì về nguyên nhân thật.
        $data = $this->postJson('rsedo/tBatch/getDataForGantt', $jar, '{}', $profile['base_url']);

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
     * Lấy các mẻ ĐÃ KẾT THÚC (status=60) từ module 车间生产 (eBatchLine/batchView) trong
     * khoảng endTime cho trước — nguồn giờ kết thúc nhuộm thật. Trả về danh sách dòng thô
     * của MES (chưa lọc máy VD — việc lọc để lệnh đồng bộ làm, vì mã máy cần chuẩn hoá).
     *
     * MES giới hạn ngầm kích thước trang (thử nghiệm 2026-08-07: pageSize>~300 hoặc thiếu
     * sortArrStr trả 0 dòng) nên cố định pageSize an toàn và tự phân trang tới hết.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBatchCompletions(
        string $endTimeStart,
        string $endTimeEnd,
        string $manuStep = 'ELASTIC_DYE',
        int $pageSize = 200,
        int $maxPages = 200,
    ): array {
        $profile = $this->profile('batch');
        $jar = new CookieJar();
        $this->login($jar, $profile);

        // Bối cảnh nhà máy/org BẮT BUỘC — thiếu thì batchView trả "未找到工厂，请添加权限！"
        // (xác nhận 2026-08-07: cùng account, có factoryCode='BPVN' -> 200/200 dòng VD đều
        // có endTime; để rỗng -> lỗi factory). Ưu tiên giá trị cấu hình; nếu trống thì tự
        // hỏi MES đúng như trang thật làm (rbase/factoryPeople/getByCode -> rows[0]).
        $batchCfg = $this->config['batch'] ?? [];
        $factoryCode = (string) ($batchCfg['factory_code'] ?? '');
        if ($factoryCode === '') {
            $factoryCode = $this->resolveFactoryCode($jar, $profile['base_url']);
        }
        $orgFilterValue = $batchCfg['org_filter_value'] ?? null;

        $all = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $body = json_encode([
                'pageNumber' => $page,
                'pageSize' => $pageSize,
                'manuStep' => $manuStep,
                'status' => '60',   // đã kết thúc
                'iscl' => 'Y',
                'endTimeStart' => $endTimeStart,
                'endTimeEnd' => $endTimeEnd,
                // Bắt buộc có, nếu không MES trả 0 dòng (xem ghi chú trên).
                'sortArrStr' => 'L.END_TIME DESC,B.BATCH_NO ASC,L.LINE_NO ASC',
                'firstSearch' => false,
                'isLike' => 'like',
                'batchState' => '',
                'factoryCode' => $factoryCode,
                'orgFilterValue' => $orgFilterValue,
            ], JSON_UNESCAPED_UNICODE);

            $data = $this->postJson('eBatchLine/batchView', $jar, $body, $profile['base_url']);

            $rows = $data['rows'] ?? null;
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }
        }

        return $all;
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

    /**
     * Gộp cấu hình đăng nhập cho một "profile": 'default' (đồng bộ màu) hoặc 'batch' (giờ
     * kết thúc nhuộm). Profile 'batch' cho phép ghi đè base_url/tài khoản/đường login riêng;
     * trường nào để trống thì tự dùng lại cấu hình chính.
     *
     * @return array{base_url: string, username: ?string, password: ?string, login_path: string}
     */
    private function profile(string $name): array
    {
        $c = $this->config;
        $defaultLogin = ltrim((string) ($c['login_path'] ?? 'sys/ssologin'), '/');

        if ($name === 'batch') {
            $b = $c['batch'] ?? [];

            return [
                'base_url' => rtrim((string) (($b['base_url'] ?? null) ?: $c['base_url']), '/'),
                'username' => ($b['username'] ?? null) ?: ($c['username'] ?? null),
                'password' => ($b['password'] ?? null) ?: ($c['password'] ?? null),
                'login_path' => ltrim((string) (($b['login_path'] ?? null) ?: $defaultLogin), '/'),
            ];
        }

        return [
            'base_url' => rtrim((string) $c['base_url'], '/'),
            'username' => $c['username'] ?? null,
            'password' => $c['password'] ?? null,
            'login_path' => $defaultLogin,
        ];
    }

    /**
     * Nhà máy mặc định của tài khoản (getByCode trả các nhà máy user thuộc về; trang thật
     * dùng phần tử đầu). Trả '' nếu không lấy được — khi đó batchView sẽ báo lỗi factory rõ
     * ràng để người vận hành biết cần cấu hình MES_BATCH_FACTORY_CODE.
     */
    private function resolveFactoryCode(CookieJar $jar, string $baseUrl): string
    {
        try {
            $data = $this->postJson('rbase/factoryPeople/getByCode', $jar, '{}', $baseUrl);
        } catch (RuntimeException) {
            return '';
        }

        $rows = $data['rows'] ?? null;

        return is_array($rows) && isset($rows[0]['factoryCode'])
            ? (string) $rows[0]['factoryCode']
            : '';
    }

    /** @param array{base_url: string, username: ?string, password: ?string, login_path: string} $profile */
    private function login(CookieJar $jar, array $profile): void
    {
        if (!$profile['username'] || !$profile['password']) {
            throw new RuntimeException('Chưa cấu hình tài khoản MES (MES_USERNAME/MES_PASSWORD hoặc MES_BATCH_*).');
        }

        $login = Http::withOptions($this->httpOptions($jar))
            ->timeout($this->timeout())
            ->asForm()
            ->post($profile['base_url'] . '/' . $profile['login_path'], [
                'username' => $profile['username'],
                'password' => $profile['password'],
            ]);

        $loginJson = $login->json();
        if (!is_array($loginJson) || ($loginJson['code'] ?? null) !== 0) {
            // Không log $password, và cũng không log nguyên body (có thể chứa token phiên).
            throw new RuntimeException(
                'Đăng nhập MES thất bại: ' . ($loginJson['msg'] ?? 'phản hồi không hợp lệ')
            );
        }
    }

    /**
     * POST body JSON thô tới 1 endpoint MES (dùng lại phiên trong $jar) và trả mảng đã
     * decode. Ném lỗi khi MES không trả JSON — thường là do phiên bị từ chối (MES trả
     * nguyên trang HTML đăng nhập kèm HTTP 200).
     *
     * @return array<string, mixed>
     */
    private function postJson(string $path, CookieJar $jar, string $jsonBody, string $baseUrl): array
    {
        $response = Http::withOptions($this->httpOptions($jar))
            ->timeout($this->timeout())
            ->withBody($jsonBody, 'application/json')
            ->post(rtrim($baseUrl, '/') . '/' . ltrim($path, '/'));

        $data = $response->json();

        if (!is_array($data)) {
            throw new RuntimeException(
                "MES không trả về JSON ở '$path' (nhiều khả năng phiên đăng nhập bị từ chối)."
            );
        }

        return $data;
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 120);
    }

    /** @return array<string, mixed> */
    private function httpOptions(CookieJar $jar): array
    {
        return [
            'cookies' => $jar,
            'verify' => (bool) ($this->config['verify_ssl'] ?? true),
        ];
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
