<?php

// backend/tests/Feature/WeighBatchTest.php
//
// Endpoint lưu CẢ MẺ 1 lần (POST /api/weighing-jobs/{id}/weigh-batch) — port đúng VBA
// scaleform.btnSave_Click của workbook "4.semiauto-small scale ... DF026-027.xlsm", dùng cho
// màn hình /weighing-station-v2 (bấm NEXT chạy hết 9 ô rồi mới SAVE). Khác hẳn weighItem
// (lưu ngay từng dòng) đang dùng ở /weighing-station — 2 luồng chạy song song, chung một
// đường ghi WeighingItemRecorder.

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\Material;
use App\Models\ProductionBatch;
use App\Models\ScaleMeasurement;
use App\Models\User;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeighBatchTest extends TestCase
{
    use RefreshDatabase;

    private $actingAsUser;

    private ProductionBatch $batch;

    /** @return array{0: WeighingJob, 1: \Illuminate\Support\Collection<int, WeighingJobItem>} */
    private function makeJobWithItems(int $count = 3): array
    {
        $this->actingAsUser = User::factory()->create(['username' => 'op_batch_'.uniqid()]);
        $machine = Machine::firstOrCreate(['code' => 'VD-BATCH-TEST'], ['name' => 'x']);
        $this->batch = ProductionBatch::create([
            'legacy_batch_id' => 'BAT'.time().rand(1, 999),
            'color' => 'C', 'product_code' => 'P',
            'machine_id' => $machine->id, 'status' => 'APPROVED',
        ]);
        $job = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
        ]);

        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            Material::firstOrCreate(['code' => "DYE-B{$i}"], ['name' => "Dye {$i}", 'type' => 'DYE']);
            $items->push(WeighingJobItem::create([
                'weighing_job_id' => $job->id,
                'material_code' => "DYE-B{$i}",
                'planned_weight' => 100.0,
                'tolerance_minus' => 1.0,
                'tolerance_plus' => 1.0,
                'sequence_no' => $i,
                'status' => 'PENDING',
            ]));
        }

        return [$job, $items];
    }

    private function rowsFor($items, float $weight = 100.0): array
    {
        return $items->values()->map(fn ($item, $idx) => [
            'item_id' => $item->id,
            'weight' => $weight,
            'rack_code' => 'R'.($idx + 1),
        ])->all();
    }

    public function test_saves_all_rows_in_one_call_and_completes_job_and_batch()
    {
        [$job, $items] = $this->makeJobWithItems(3);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $this->rowsFor($items),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.job_completed', true);

        foreach ($items as $item) {
            $fresh = WeighingJobItem::find($item->id);
            $this->assertEquals('COMPLETED', $fresh->status);
            $this->assertEquals(100.0, (float) $fresh->actual_weight);
            // Mỗi dòng phải sinh đúng 1 bản ghi lịch sử cân (bất biến, phục vụ đối soát).
            $this->assertEquals(1, ScaleMeasurement::where('weighing_job_item_id', $item->id)->count());
        }

        $this->assertEquals('COMPLETED', WeighingJob::find($job->id)->status);
        $this->assertEquals('WEIGHED', ProductionBatch::find($this->batch->id)->status);
    }

    /**
     * Bấm SAVE lại sau khi rớt mạng: dòng đã cân xong phải bị BỎ QUA, không được ném lỗi làm
     * hỏng cả mẻ (mất hết số thao tác viên vừa cân, vì V2 giữ giá trị ở client tới lúc SAVE).
     */
    public function test_second_save_skips_already_completed_rows_without_failing()
    {
        [$job, $items] = $this->makeJobWithItems(3);
        $payload = [
            'rows' => $this->rowsFor($items),
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
        ];

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", $payload)
            ->assertStatus(200);

        $second = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", $payload);

        $second->assertStatus(200);
        $second->assertJsonCount(0, 'data.saved_item_ids');
        $second->assertJsonCount(3, 'data.skipped_item_ids');

        // Không được ghi thêm bản ghi cân trùng cho lần bấm thứ 2.
        foreach ($items as $item) {
            $this->assertEquals(1, ScaleMeasurement::where('weighing_job_item_id', $item->id)->count());
        }
    }

    /**
     * Port đúng VBA btnSave_Click: `For i = 1 To 9: If Trim(txt_weight{i}) <> "" Then INSERT`
     * — MỌI dòng có WEIGHT mục tiêu đều được ghi, kể cả ô PROCESS còn trống (chưa hề cân).
     * Dòng chưa cân gửi weight = null, phải được chốt COMPLETED và gắn REJECTED (VBA: nền ô
     * PROCESS không xanh -> processColor = REJECTED).
     */
    public function test_saves_unweighed_rows_as_rejected_like_vba()
    {
        [$job, $items] = $this->makeJobWithItems(3);

        $rows = $this->rowsFor($items);
        $rows[1]['weight'] = null;   // ô giữa chưa cân
        $rows[2]['weight'] = null;   // ô cuối chưa cân

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $rows,
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ])->assertStatus(200);

        $weighed = WeighingJobItem::find($items[0]->id);
        $this->assertEquals('COMPLETED', $weighed->status);
        $this->assertEquals(100.0, (float) $weighed->actual_weight);
        $this->assertEquals('ACCEPTED', $weighed->process_status);

        foreach ([1, 2] as $i) {
            $skipped = WeighingJobItem::find($items[$i]->id);
            $this->assertEquals('COMPLETED', $skipped->status, 'Dòng chưa cân vẫn phải được chốt như VBA');
            $this->assertNull($skipped->actual_weight, 'Không được bịa số cân cho dòng chưa cân');
            $this->assertEquals('REJECTED', $skipped->process_status);
        }

        // Mẻ coi như đã đóng — đúng VBA (SAVE xong là xoá form, không quay lại cân tiếp).
        $this->assertEquals('COMPLETED', WeighingJob::find($job->id)->status);
    }

    /**
     * Quét lại đúng mã QR sau khi đã SAVE = CÂN LẠI TỪ ĐẦU (yêu cầu 2026-08-01), nên
     * ScannerController KHÔNG được tái dùng job đã COMPLETED. Nếu tái dùng, 9 dòng hiện nguyên
     * số cũ và weighBatch bỏ qua hết (dòng đã COMPLETED) — màn hình đứng im không cân được gì.
     *
     * Job cũ phải còn nguyên: không xoá vật lý dữ liệu giao dịch (CLAUDE.md mục 3).
     */
    public function test_completed_job_is_not_reused_so_rescan_starts_a_new_round()
    {
        [$job, $items] = $this->makeJobWithItems(2);

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $this->rowsFor($items),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ])->assertStatus(200);

        $this->assertEquals('COMPLETED', WeighingJob::find($job->id)->status);

        // Đúng truy vấn ScannerController::handleOrderScan dùng để tìm job tái sử dụng.
        $reusable = WeighingJob::where('production_batch_id', $this->batch->id)
            ->where('job_type', 'DYE')
            ->where('status', '!=', 'COMPLETED')
            ->where('assigned_workstation_id', $job->assigned_workstation_id)
            ->orderByDesc('created_at')
            ->first();

        $this->assertNull($reusable, 'Job đã COMPLETED không được coi là tái sử dụng được');
        $this->assertNotNull(WeighingJob::find($job->id), 'Job cũ phải còn nguyên để đối soát');
    }

    private function makeStation(string $code): Workstation
    {
        return Workstation::create([
            'code' => $code,
            'name' => $code,
            'type' => 'DYE_WEIGHING',
            'workstation_type' => 'DYE_WEIGHING',
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * HAI MÁY CÂN CÙNG MỘT ĐƠN (2026-08-01, "chả máy nào ảnh hưởng máy nào").
     *
     * Trước bản vá, handleOrderScan tìm job tái dùng KHÔNG lọc theo trạm nên hai máy nhận về
     * CÙNG một WeighingJob: máy bấm SAVE sau bị bỏ qua toàn bộ những dòng máy kia đã ghi
     * (weighBatch bỏ dòng COMPLETED) — mất số mà không ai biết. Nay mỗi máy một vòng cân riêng.
     */
    public function test_two_machines_weighing_the_same_batch_both_save_in_full()
    {
        [$jobA, $itemsA] = $this->makeJobWithItems(3);

        $mayA = $this->makeStation('WS-SCALE-MAY-A');
        $mayB = $this->makeStation('WS-SCALE-MAY-B');
        $jobA->assigned_workstation_id = $mayA->id;
        $jobA->save();

        // Máy B quét cùng đơn -> KHÔNG được vớ phải job của máy A.
        $viB = WeighingJob::where('production_batch_id', $this->batch->id)
            ->where('job_type', 'DYE')
            ->where('status', '!=', 'COMPLETED')
            ->where('assigned_workstation_id', $mayB->id)
            ->first();
        $this->assertNull($viB, 'Máy B không được tái dùng vòng cân của máy A');

        // ...nên nó có vòng cân riêng.
        $jobB = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
            'assigned_workstation_id' => $mayB->id,
        ]);
        $itemsB = collect();
        foreach ($itemsA as $i => $src) {
            $itemsB->push(WeighingJobItem::create([
                'weighing_job_id' => $jobB->id,
                'material_code' => $src->material_code,
                'planned_weight' => 100.0,
                'tolerance_minus' => 1.0,
                'tolerance_plus' => 1.0,
                'sequence_no' => $i + 1,
                'status' => 'PENDING',
            ]));
        }

        // Cả hai máy đều SAVE, số khác nhau để chứng minh không ai đè ai.
        foreach ([[$jobA, $itemsA, 100.0], [$jobB, $itemsB, 55.0]] as [$job, $items, $kg]) {
            $this->actingAs($this->actingAsUser)
                ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                    'rows' => $this->rowsFor($items, $kg),
                    'scale_device_id' => 'MOCK_SCALE',
                    'stable' => true,
                ])->assertStatus(200);
        }

        // Không dòng nào bị bỏ qua ở cả hai bên — đây chính là thứ hỏng trước bản vá.
        foreach ($itemsA as $item) {
            $this->assertEquals(100.0, (float) WeighingJobItem::find($item->id)->actual_weight);
        }
        foreach ($itemsB as $item) {
            $this->assertEquals(55.0, (float) WeighingJobItem::find($item->id)->actual_weight);
        }

        $this->assertEquals('COMPLETED', WeighingJob::find($jobA->id)->status);
        $this->assertEquals('COMPLETED', WeighingJob::find($jobB->id)->status);

        // Lô chỉ được WEIGHED khi MỌI vòng cân xong — cascade đếm theo tất cả job của lô.
        $this->assertEquals('WEIGHED', ProductionBatch::find($this->batch->id)->status);
    }

    /**
     * Máy A cân xong trước trong khi máy B còn dở: lô phải là PARTIALLY_WEIGHED, KHÔNG được
     * nhảy sang WEIGHED — nếu không, trạm vận chuyển sẽ nhận thùng khi mẻ chưa cân xong.
     */
    public function test_batch_is_not_marked_weighed_while_the_other_machine_is_still_weighing()
    {
        [$jobA, $itemsA] = $this->makeJobWithItems(2);

        $jobA->assigned_workstation_id = $this->makeStation('WS-SCALE-A2')->id;
        $jobA->save();

        $jobB = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
            'assigned_workstation_id' => $this->makeStation('WS-SCALE-B2')->id,
        ]);
        WeighingJobItem::create([
            'weighing_job_id' => $jobB->id,
            'material_code' => $itemsA->first()->material_code,
            'planned_weight' => 100.0,
            'tolerance_minus' => 1.0,
            'tolerance_plus' => 1.0,
            'sequence_no' => 1,
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$jobA->id}/weigh-batch", [
                'rows' => $this->rowsFor($itemsA),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ])->assertStatus(200);

        $this->assertEquals('PARTIALLY_WEIGHED', ProductionBatch::find($this->batch->id)->status);
    }

    /** Cùng hàng rào với weighItem — client gọi thẳng API không được lách qua UI. */
    public function test_rejected_when_stable_false()
    {
        [$job, $items] = $this->makeJobWithItems(2);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $this->rowsFor($items),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => false,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'NOT_STABLE');
        $this->assertEquals('PENDING', WeighingJobItem::find($items->first()->id)->status);
    }

    /** Gửi item của mẻ khác thì phải chặn cả mẻ, không ghi nhầm sang job khác. */
    public function test_rejects_rows_not_belonging_to_job()
    {
        [$job, $items] = $this->makeJobWithItems(2);
        [, $otherItems] = $this->makeJobWithItems(1);

        $rows = $this->rowsFor($items);
        $rows[] = ['item_id' => $otherItems->first()->id, 'weight' => 50.0];

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $rows,
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'ITEM_NOT_IN_JOB');
        $this->assertEquals('PENDING', WeighingJobItem::find($items->first()->id)->status);
    }

    /**
     * scale_measurements có UNIQUE(legacy_source, legacy_id) — cách sinh cũ time()+rand có thể
     * trùng khi ghi 9 dòng trong cùng 1 giây, làm hỏng nguyên cả mẻ. Xác nhận 9 dòng ra 9
     * legacy_id khác nhau.
     */
    public function test_nine_rows_get_distinct_legacy_ids()
    {
        [$job, $items] = $this->makeJobWithItems(9);

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
                'rows' => $this->rowsFor($items),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ])->assertStatus(200);

        $legacyIds = ScaleMeasurement::whereIn('weighing_job_item_id', $items->pluck('id'))
            ->pluck('legacy_id');

        $this->assertCount(9, $legacyIds);
        $this->assertCount(9, $legacyIds->unique());
    }

    /**
     * POST /api/weighing-jobs/{id}/cancel (2026-08-01) — dọn một vòng cân CHƯA HỀ GHI GÌ, để
     * quét-nhầm-rồi-bỏ-đi không để lại job mồ côi khiến lô không bao giờ về được WEIGHED.
     */
    public function test_cancel_marks_empty_job_as_cancelled()
    {
        [$job] = $this->makeJobWithItems(2);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals('CANCELLED', WeighingJob::find($job->id)->status);
    }

    /** Job đã có ít nhất 1 dòng cân THẬT thì không được hủy trắng — đó là dữ liệu giao dịch. */
    public function test_cancel_rejects_job_with_completed_items()
    {
        [$job, $items] = $this->makeJobWithItems(3);

        // Cân 1 dòng thật qua weighItem (tương đương /weighing-station), 2 dòng còn lại vẫn PENDING.
        $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/items/{$items[0]->id}/weigh", [
            'weight' => 100.0,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
        ])->assertStatus(200);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/cancel");

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'JOB_HAS_COMPLETED_ITEMS');
        $this->assertNotEquals('CANCELLED', WeighingJob::find($job->id)->status);
    }

    /** Job đã SAVE xong (COMPLETED) thì không còn gì để hủy. */
    public function test_cancel_rejects_already_completed_job()
    {
        [$job, $items] = $this->makeJobWithItems(1);

        $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/{$job->id}/weigh-batch", [
            'rows' => $this->rowsFor($items),
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
        ])->assertStatus(200);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/cancel");

        $response->assertStatus(409);
        $response->assertJsonPath('error_code', 'JOB_ALREADY_COMPLETED');
    }

    /** Hủy 2 lần liên tiếp không được lỗi — thao tác viên có thể bấm CLEAR nhiều lần. */
    public function test_cancel_is_idempotent()
    {
        [$job] = $this->makeJobWithItems(1);

        $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/{$job->id}/cancel")->assertStatus(200);

        $response = $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$job->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals('CANCELLED', WeighingJob::find($job->id)->status);
    }

    /**
     * Job bị hủy KHÔNG được tính vào cascade trạng thái lô — nếu không, một lần quét-nhầm-rồi-
     * hủy sẽ khiến lô kẹt ở PARTIALLY_WEIGHED vĩnh viễn dù vòng cân thật (job còn lại) đã xong.
     */
    public function test_cancelled_job_is_excluded_from_batch_cascade()
    {
        [$jobReal, $itemsReal] = $this->makeJobWithItems(2);

        // Vòng cân THẬT ở máy A.
        $jobReal->assigned_workstation_id = $this->makeStation('WS-CASCADE-REAL')->id;
        $jobReal->save();

        // Vòng cân QUÉT NHẦM ở máy B — cùng lô, cùng loại, chưa cân dòng nào.
        $jobNham = WeighingJob::create([
            'production_batch_id' => $this->batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
            'assigned_workstation_id' => $this->makeStation('WS-CASCADE-NHAM')->id,
        ]);
        WeighingJobItem::create([
            'weighing_job_id' => $jobNham->id,
            'material_code' => $itemsReal->first()->material_code,
            'planned_weight' => 100.0,
            'tolerance_minus' => 1.0,
            'tolerance_plus' => 1.0,
            'sequence_no' => 1,
            'status' => 'PENDING',
        ]);

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$jobNham->id}/cancel")->assertStatus(200);

        $this->actingAs($this->actingAsUser)
            ->postJson("/api/weighing-jobs/{$jobReal->id}/weigh-batch", [
                'rows' => $this->rowsFor($itemsReal),
                'scale_device_id' => 'MOCK_SCALE',
                'stable' => true,
            ])->assertStatus(200);

        // Không có job nào khác (ngoài job đã hủy) -> lô phải WEIGHED, không kẹt ở PARTIALLY_WEIGHED.
        $this->assertEquals('WEIGHED', ProductionBatch::find($this->batch->id)->status);
    }

    /**
     * Job đã CANCELLED không được coi là "tái sử dụng được" khi quét lại cùng batch/trạm — đúng
     * truy vấn ScannerController::handleOrderScan dùng, quét lại phải mở MỘT VÒNG CÂN MỚI.
     */
    public function test_cancelled_job_is_not_reusable_on_rescan()
    {
        [$job] = $this->makeJobWithItems(1);
        $job->assigned_workstation_id = $this->makeStation('WS-RESCAN-CANCEL')->id;
        $job->save();

        $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/{$job->id}/cancel")->assertStatus(200);

        $reusable = WeighingJob::where('production_batch_id', $this->batch->id)
            ->where('job_type', 'DYE')
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->where('assigned_workstation_id', $job->assigned_workstation_id)
            ->first();

        $this->assertNull($reusable, 'Job đã CANCELLED không được coi là tái sử dụng được');
    }
}
