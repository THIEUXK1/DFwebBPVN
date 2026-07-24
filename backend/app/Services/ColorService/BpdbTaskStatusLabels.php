<?php
// backend/app/Services/ColorService/BpdbTaskStatusLabels.php
//
// Tên hiển thị TRUNG TÍNH cho SUP_Tasks.TaskStatus (yêu cầu 2026-07-21) — KHÔNG được
// đặt tên ngụ ý công đoạn vật lý (vd "Đang cấp máy", "Đã cấp xong") vì đã xác nhận bằng
// dữ liệu thật là không có bằng chứng: TaskStatus=30 có thể đang ở pha khác trước khi
// dosing xuất hiện trong SUP_Storico; TaskStatus=99 vẫn có thể có nhiều đợt dosing thật
// (xem báo cáo trace 2026-07-21, task ES79130-T7020).

namespace App\Services\ColorService;

class BpdbTaskStatusLabels
{
    private const LABELS = [
        '10' => 'Chờ hệ thống xử lý',
        '20' => 'Đang chuyển trạng thái',
        '30' => 'Đang được hệ thống xử lý',
        '40' => 'Task đã kết thúc',
        '99' => 'Task bị hủy/xóa',
    ];

    public static function neutral(null|string|int $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        return self::LABELS[(string) $code] ?? 'Trạng thái không xác định (' . $code . ')';
    }
}
