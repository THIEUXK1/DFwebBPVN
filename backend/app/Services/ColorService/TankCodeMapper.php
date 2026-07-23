<?php
// backend/app/Services/ColorService/TankCodeMapper.php
//
// Quy đổi Tank số thuần của BPDB (DyeMachines.Tank = 1/2/3/4) sang mã chữ+số dùng
// trong VBA/jit_routing_rules/app.tanks (1A/2B/3C/4D). Dùng CHUNG duy nhất ở đây —
// không lặp logic quy đổi ở nơi khác (mục 7, "YÊU CẦU KHÓA MODULE GIÁM SÁT BPDB").

namespace App\Services\ColorService;

class TankCodeMapper
{
    private const MAP = [
        1 => '1A',
        2 => '2B',
        3 => '3C',
        4 => '4D',
    ];

    /** Trả về null nếu không map được (KHÔNG tự đoán/gán mặc định). */
    public static function toLetterCode(int|string|null $numericTank): ?string
    {
        if ($numericTank === null || $numericTank === '') {
            return null;
        }
        $n = (int) $numericTank;
        return self::MAP[$n] ?? null;
    }

    public static function toNumeric(string $letterCode): ?int
    {
        $flipped = array_flip(self::MAP);
        return $flipped[strtoupper(trim($letterCode))] ?? null;
    }
}
