<?php
// backend/app/Services/ColorService/ColorCodePalette.php
//
// Suy ra màu hiển thị gần đúng của một mẻ nhuộm từ MÃ MÀU (vd "AP86138", "B21341").
//
// Vì sao phải suy ra thay vì tra màu thật:
//   Chuyền VD (VD001-VD018, VDG01-VDG08) chạy trên bộ điều khiển Copower/BPDB, KHÔNG có
//   mặt trong VN-MES. Đã đối chiếu và xác nhận không có nguồn RGB thật nào:
//     - MES Sedo Planboard: có cRgbcolor thật nhưng chỉ phủ máy DY/VB/VJ/VK/VM/VR — 0 máy VD.
//     - MES batchFollow/listBatch: 138.293 mẻ, tra LIKE hoạt động, nhưng 0 kết quả cho mọi
//       mã màu lẫn mã hàng của chuyền VD; trường colorRgb luôn NULL.
//     - BPDB (SQL Server): 16 bảng, không bảng nào có cột màu.
//   => Không tồn tại RGB thật cho mẻ VD trong bất kỳ hệ thống nào truy cập được.
//
// Cách suy ra:
//   Cả hai chuyền dùng CHUNG quy ước đặt mã màu:  [L|F|M]* HO [P|S|+] [Z|W|T|B]*
//     HO  : A đỏ, B xanh dương, C cam, D xanh lục, E nâu, G xám, H đen, V tím, W trắng, Y vàng
//     SẮC : P = nhạt/pastel, S = trung, '+' hoặc để trống = đậm
//   Bảng tra (HO+SẮC) -> hex được tính bằng TRUNG BÌNH màu thật của 461 swatch MES trong
//   bảng color_swatches (lệnh mes:sync-color-swatches). Kiểm chứng: độ lệch chuẩn trong
//   từng nhóm rất thấp (vd AP: 5,11,12 trên thang 255) => tiền tố đúng là họ màu.
//   Độ phủ đo trên 1.630 mã màu VD thực tế: 97,7% khớp HO+SẮC, 0,8% khớp HO, 1,5% không khớp.
//
// GIỚI HẠN: đây là màu ĐẠI DIỆN CHO HỌ MÀU, không phải màu đo được của mẻ. Dùng để phân biệt
// nhanh trên biểu đồ Gantt, KHÔNG dùng để đối chiếu chất lượng màu.

namespace App\Services\ColorService;

use App\Models\ColorSwatch;
use Illuminate\Support\Facades\Cache;

class ColorCodePalette
{
    private const CACHE_KEY = 'color_code_palette_v1';
    private const CACHE_TTL = 3600;

    /** Dùng khi không giải mã được mã màu — xám trung tính, không gợi ý sai họ màu nào. */
    public const FALLBACK_HEX = '#9AA0A6';

    /** @var array{tone: array<string,string>, family: array<string,string>}|null */
    private ?array $palette = null;

    public function hexFor(?string $colorCode): string
    {
        if (!$colorCode) {
            return self::FALLBACK_HEX;
        }

        $decoded = self::decode($colorCode);
        if (!$decoded) {
            return self::FALLBACK_HEX;
        }

        [$family, $tone] = $decoded;
        $palette = $this->palette();

        return $palette['tone'][$family . $tone]
            ?? $palette['family'][$family]
            ?? self::FALLBACK_HEX;
    }

    /**
     * Tách mã màu thành [họ màu, sắc độ].
     *
     * @return array{0: string, 1: string}|null
     */
    public static function decode(string $colorCode): ?array
    {
        if (!preg_match('/^([A-Za-z]+\+?)/', trim($colorCode), $m)) {
            return null;
        }

        // L (vải nhẹ), F, M là tiền tố biến thể của mã hàng — không đổi họ màu.
        // Chỉ bóc khi phía sau vẫn còn chữ cái, tránh nuốt mất chính họ màu (vd "M...", "F...").
        $prefix = preg_replace('/^[LFM]+(?=[A-Z])/', '', strtoupper($m[1]));
        if ($prefix === '') {
            return null;
        }

        $family = $prefix[0];
        $rest = substr($prefix, 1);

        $tone = match (true) {
            str_starts_with($rest, 'P') => 'P',
            str_starts_with($rest, 'S') => 'S',
            default => '+',
        };

        return [$family, $tone];
    }

    /** @return array{tone: array<string,string>, family: array<string,string>} */
    private function palette(): array
    {
        if ($this->palette !== null) {
            return $this->palette;
        }

        $this->palette = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $toneBuckets = [];
            $familyBuckets = [];

            ColorSwatch::query()
                ->select(['color_code', 'rgb_hex'])
                ->chunk(500, function ($rows) use (&$toneBuckets, &$familyBuckets) {
                    foreach ($rows as $row) {
                        $decoded = self::decode($row->color_code);
                        if (!$decoded) {
                            continue;
                        }
                        $rgb = self::hexToRgb($row->rgb_hex);
                        if (!$rgb) {
                            continue;
                        }
                        $toneBuckets[$decoded[0] . $decoded[1]][] = $rgb;
                        $familyBuckets[$decoded[0]][] = $rgb;
                    }
                });

            return [
                'tone' => array_map(self::averageHex(...), $toneBuckets),
                'family' => array_map(self::averageHex(...), $familyBuckets),
            ];
        });

        return $this->palette;
    }

    /** @param array<int, array{0:int,1:int,2:int}> $rgbList */
    private static function averageHex(array $rgbList): string
    {
        $n = count($rgbList);
        $sum = [0, 0, 0];
        foreach ($rgbList as $rgb) {
            $sum[0] += $rgb[0];
            $sum[1] += $rgb[1];
            $sum[2] += $rgb[2];
        }

        return sprintf('#%02X%02X%02X', (int) round($sum[0] / $n), (int) round($sum[1] / $n), (int) round($sum[2] / $n));
    }

    /** @return array{0:int,1:int,2:int}|null */
    private static function hexToRgb(?string $hex): ?array
    {
        if (!$hex || !preg_match('/^#?([0-9A-Fa-f]{6})$/', trim($hex), $m)) {
            return null;
        }

        return [
            (int) hexdec(substr($m[1], 0, 2)),
            (int) hexdec(substr($m[1], 2, 2)),
            (int) hexdec(substr($m[1], 4, 2)),
        ];
    }
}
