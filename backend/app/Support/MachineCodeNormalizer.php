<?php
// backend/app/Support/MachineCodeNormalizer.php
//
// Chuẩn hoá mã máy để khớp giữa 2 nguồn đặt tên KHÁC NHAU:
//   - BPDB (DyeMachines.MachineNo): pad số kiểu 'VD001', 'VD003'
//   - MES  (eBatchLine.machineNo): 'VD05', 'VD06', 'VD202' (pad không nhất quán)
// Cùng 1 máy vật lý nhưng chuỗi khác nhau -> phải quy về một dạng chung trước khi so.
//
// Quy tắc: tách tiền tố chữ + hậu tố số, bỏ số 0 ở đầu phần số:
//   'VD05' -> 'VD5',  'VD005' -> 'VD5',  'VD202' -> 'VD202',  'vd 05' -> 'VD5'
// Không có phần số ở đuôi thì trả nguyên (đã uppercase/trim). Đây là helper DUY NHẤT cho
// việc chuẩn hoá này — dùng chung ở lệnh đồng bộ và ở bước ghép Gantt để không lệch logic.

namespace App\Support;

class MachineCodeNormalizer
{
    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return '';
        }

        // Tách "<chữ/ký tự đầu><số ở đuôi>". Chỉ chuẩn hoá khi có cụm số ở CUỐI.
        if (preg_match('/^(.*?)(\d+)$/', $code, $m)) {
            $prefix = rtrim($m[1]); // bỏ khoảng trắng lọt giữa 'VD 05'
            $number = (int) $m[2];  // '005' -> 5

            return $prefix . $number;
        }

        return $code;
    }
}
