<?php
// backend/app/Services/WeighingItemRecorder.php
//
// Tách nguyên văn từ thân DB::transaction của WeighingJobController::weighItem để 2 luồng
// dùng chung MỘT đường ghi duy nhất:
//   - weighItem     : lưu từng dòng (màn hình /weighing-station, cân xong dòng nào lưu ngay)
//   - weighBatch    : lưu cả mẻ 1 lần (màn hình /weighing-station-v2, port đúng VBA
//                     scaleform.btnSave_Click — bấm NEXT chạy hết 9 ô rồi mới SAVE)
//
// KHÔNG tự mở transaction ở đây — caller quyết định phạm vi transaction, vì luồng batch cần
// cả 9 dòng nằm trong CÙNG 1 transaction (mất điện giữa chừng thì không được lưu nửa vời).

namespace App\Services;

use App\Models\ScaleMeasurement;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WeighingItemRecorder
{
    /**
     * Ghi kết quả cân cho ĐÚNG 1 vật tư + cascade trạng thái job/batch.
     *
     * `weight` = null nghĩa là dòng này ĐƯỢC CHỐT nhưng KHÔNG hề cân — chỉ xảy ra ở luồng lưu
     * cả mẻ (VBA btnSave_Click ghi mọi dòng có WEIGHT mục tiêu, kể cả ô PROCESS còn trống).
     * Khi đó actual_weight để null và WeighingJobItem::getProcessStatusAttribute trả REJECTED,
     * đúng như VBA gắn processColor=REJECTED cho ô nền không xanh.
     *
     * @param  array{weight: float|null, tare_weight?: float|null, gross_weight?: float|null, rack_code?: string|null, legacy_id?: int}  $data
     * @return array{item: WeighingJobItem, job_completed: bool, next_item: WeighingJobItem|null}
     */
    public function record(WeighingJobItem $item, array $data): array
    {
        $job = $item->job;
        $batch = $job->batch;

        $measuredWeight = ($data['weight'] ?? null) === null ? null : (float) $data['weight'];
        $tareWeight = $data['tare_weight'] ?? null;
        $grossWeight = $data['gross_weight'] ?? null;
        $rackCode = $data['rack_code'] ?? null;

        // Save to scale measurements (create new for every weigh attempt, no overwrites)
        ScaleMeasurement::create([
            'legacy_source' => 'web_app',
            'legacy_id' => $data['legacy_id'] ?? $this->nextLegacyId(),
            'legacy_batch_id' => $batch->legacy_batch_id,
            'color' => $batch->color,
            'product_code' => $batch->product_code,
            'machine_code' => $batch->machine ? $batch->machine->code : 'N/A',
            'level_code' => $batch->level_code,
            'rack_code' => $rackCode,
            'dye_code' => $item->material_code,
            'weight' => $measuredWeight,
            'tare_weight' => $tareWeight,
            'gross_weight' => $grossWeight,
            'measured_at' => Carbon::now(),
            'material_type' => $job->job_type === 'DYE' ? 'DYE' : 'CHEMICAL',
            'weighing_job_item_id' => $item->id,
        ]);

        // Update item details
        $item->actual_weight = $measuredWeight;
        $item->rack_code = $rackCode;
        $item->status = 'COMPLETED';
        $item->completed_at = Carbon::now();
        $item->save();

        // Check if job is completed
        $unfinishedItems = WeighingJobItem::where('weighing_job_id', $job->id)
            ->where('status', '!=', 'COMPLETED')
            ->count();

        if ($unfinishedItems === 0) {
            $job->status = 'COMPLETED';
            $job->completed_at = Carbon::now();
            $job->save();

            // Trigger alerts rule validation
            RealtimeService::publish('weighing_job.completed', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);

            // Update Overall Production Batch Status based on remaining jobs
            // A production batch is marked as "WEIGHED" only when ALL generated jobs are COMPLETED.
            // If some jobs are completed but others are not, status is "PARTIALLY_WEIGHED".
            //
            // Loại CANCELLED (2026-08-01, WeighingJobController::cancel): vòng cân bị hủy trắng
            // (quét nhầm rồi bỏ đi trước khi cân dòng nào) không phải một vòng cân THẬT của lô —
            // đếm nó vào $totalJobsCount sẽ khiến lô KHÔNG BAO GIỜ về được WEIGHED, vì job đã
            // hủy không bao giờ tự chuyển COMPLETED.
            $allJobs = WeighingJob::where('production_batch_id', $batch->id)
                ->where('status', '!=', 'CANCELLED')
                ->get();
            $completedJobs = $allJobs->where('status', 'COMPLETED')->count();
            $totalJobsCount = $allJobs->count();

            if ($completedJobs === $totalJobsCount) {
                $batch->status = 'WEIGHED';
            } else {
                $batch->status = 'PARTIALLY_WEIGHED';
            }
            $batch->save();
        } else {
            $job->status = 'IN_PROGRESS';
            $job->save();
        }

        // Return next pending item in sequence
        $nextItem = WeighingJobItem::where('weighing_job_id', $job->id)
            ->where('status', '!=', 'COMPLETED')
            ->orderBy('sequence_no', 'asc')
            ->first();

        return [
            'item' => $item,
            'job_completed' => ($unfinishedItems === 0),
            'next_item' => $nextItem,
        ];
    }

    /**
     * scale_measurements có UNIQUE(legacy_source, legacy_id). Cách sinh cũ `time()+rand(1,1e5)`
     * an toàn khi mỗi request chỉ ghi 1 dòng, nhưng luồng batch ghi tới 9 dòng trong cùng một
     * giây — xác suất trùng đủ để thỉnh thoảng làm hỏng NGUYÊN CẢ MẺ (mất hết số thao tác viên
     * vừa cân, vì V2 giữ giá trị ở client tới lúc bấm SAVE). Lấy max+1 để luôn tăng dần và
     * không bao giờ đụng nhau trong cùng transaction.
     */
    public function nextLegacyId(): int
    {
        $max = (int) DB::table('scale_measurements')
            ->where('legacy_source', 'web_app')
            ->max('legacy_id');

        return $max + 1;
    }
}
