<?php
// backend/app/Models/MesBatchCompletion.php
//
// Cache giờ kết thúc nhuộm thật của mẻ, đồng bộ từ MES eBatchLine (status=60).
// Xem migration create_mes_batch_completions_table + SyncMesBatchCompletionsCommand.

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MesBatchCompletion extends Model
{
    protected $table = 'mes_batch_completions';

    protected $fillable = [
        'batch_no',
        'line_no',
        'machine_code',
        'machine_no_raw',
        'color_code',
        'article_code',
        'order_ucode',
        'begin_time',
        'end_time',
        'end_by_name',
        'shift',
        'manu_step',
        'status',
        'source',
        'synced_at',
        'raw',
    ];

    protected $casts = [
        // begin_time/end_time CỐ Ý không khai báo ở đây — xem accessor bên dưới.
        'synced_at' => 'datetime',
        'raw' => 'array',
    ];

    /**
     * Giờ MES lưu trong 2 cột này là GIỜ TƯỜNG (wall clock) Việt Nam, nhưng nằm trong cột
     * timestamptz và bị Postgres gán nhãn UTC lúc ghi — nên đọc thẳng ra sẽ CHẬM 7 TIẾNG.
     *
     * Đường đi của dữ liệu (đối soát 2026-08-23 trên dữ liệu thật, mẻ V2608090010/VD05):
     *   MES trả chuỗi '2026-08-23 11:25:58' (giờ VN, xem cột raw->endTime)
     *   -> SyncMesBatchCompletionsCommand::parseVnTime() dựng Carbon 11:25:58+07:00
     *   -> Eloquent ghi xuống bằng format 'Y-m-d H:i:s' KHÔNG kèm offset ('11:25:58')
     *   -> session Postgres bị ép về UTC (config/database.php) nên hiểu thành 11:25:58 UTC
     *   => instant lưu trong DB = 18:25 giờ VN, muộn hơn thực tế đúng 7 tiếng.
     *
     * Hệ quả nhìn thấy được: thanh Gantt/biểu đồ trạng thái của mẻ đã nhuộm xong vẫn kéo dài
     * quá vạch "hiện tại", máy đã rảnh mà màn hình vẫn báo đang chạy thêm 7 tiếng nữa.
     *
     * Sửa tại ĐÚNG MỘT CHỖ này (biên đọc) thay vì rải rác nơi dùng: lấy lại các thành phần
     * giờ-phút-giây của mốc đang lưu rồi gán đúng múi giờ VN. KHÔNG trừ cứng 7 tiếng để nếu
     * mai này Postgres/PHP đổi cấu hình múi giờ thì cũng không âm thầm lệch tiếp.
     *
     * CẢNH BÁO cho người sửa sau: dữ liệu TRONG DB vẫn đang lệch 7 tiếng — mọi truy vấn SQL
     * trực tiếp lên 2 cột này (báo cáo, đối soát) đều đọc ra giờ sai. Muốn dứt điểm thì phải
     * sửa đường GHI *và* nắn lại dữ liệu cũ; khi đó BẮT BUỘC gỡ 2 accessor này, nếu không sẽ
     * lệch ngược 7 tiếng theo chiều kia.
     */
    protected function beginTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => $this->asVnWallClock($value));
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(get: fn ($value) => $this->asVnWallClock($value));
    }

    private function asVnWallClock(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $stored = Carbon::parse($value)->utc();

        return Carbon::create(
            $stored->year,
            $stored->month,
            $stored->day,
            $stored->hour,
            $stored->minute,
            $stored->second,
            'Asia/Ho_Chi_Minh'
        );
    }
}
