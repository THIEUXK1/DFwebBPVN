<?php
// backend/tests/Feature/SmallScaleTwoStationIsolationTest.php
//
// Kịch bản C (audit "Tách riêng CHEMICAL_CALL...", 2026-07-17, Mục 10/14): hai trạm
// SMALL_SCALE dùng chung module nhưng phải hoàn toàn độc lập dữ liệu runtime — job,
// item, cân sample không được trộn giữa 2 trạm khi xử lý 2 đơn khác nhau đồng thời.
// Trước bản vá này CHỈ được xác nhận bằng đọc code (WeighingJobController không thấy
// điều kiện lọc theo operation_client_id) — audit đã đánh dấu "P1, CHƯA XÁC NHẬN".
// Test này verify BẰNG THỰC NGHIỆM (không suy diễn thêm).

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Workstation;
use App\Models\Capability;
use App\Services\QrPayloadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class SmallScaleTwoStationIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeOrderAndDispatch(string $suffix, string $machineCode): array
    {
        $machine = Machine::create(['code' => $machineCode, 'name' => 'M ' . $suffix, 'is_active' => true]);
        $material = Material::create(['code' => 'MAT' . $suffix, 'name' => 'Mat ' . $suffix, 'type' => 'DYE', 'is_active' => true]);
        $recipe = Recipe::create(['color_code' => 'COL' . $suffix, 'product_code' => 'CODE' . $suffix, 'description' => $suffix]);
        $version = RecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'ACTIVE']);
        RecipeMaterial::create([
            'recipe_version_id' => $version->id,
            'material_code' => $material->code,
            'concentration' => 1.0,
            'process_code' => 'HS',
        ]);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'ISO-' . $suffix . '-' . uniqid(),
            'color' => 'COL' . $suffix,
            'product_code' => 'CODE' . $suffix,
            'machine_id' => $machine->id,
            'cloth_weight' => 200.0,
            'status' => 'APPROVED',
        ]);

        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'CONFIRMED',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
        ]);

        $rawQr = app(QrPayloadService::class)->buildDyePayload($dispatch, $batch);

        return [$batch, $dispatch, $rawQr];
    }

    /**
     * Hai trạm cân độc lập (mô phỏng SMALL_SCALE_01/02 dùng chung module DYE_WEIGHING),
     * mỗi trạm xử lý 1 đơn khác nhau — job/item không được trộn.
     */
    public function test_two_stations_processing_different_qr_do_not_mix_jobs_or_items(): void
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'iso_test_' . uniqid();
        $user->password_hash = password_hash('pw', PASSWORD_BCRYPT);
        $user->save();
        Sanctum::actingAs($user);

        $scanCap = Capability::firstOrCreate(['code' => 'SCAN_QR'], ['name' => 'SCAN_QR', 'category' => 'DEVICE']);

        $stationA = Workstation::create(['code' => 'SMALL-SCALE-A', 'name' => 'Small Scale A', 'type' => 'DYE_WEIGHING', 'active' => true]);
        $stationA->capabilities()->attach($scanCap->id, ['enabled' => true]);
        $stationB = Workstation::create(['code' => 'SMALL-SCALE-B', 'name' => 'Small Scale B', 'type' => 'DYE_WEIGHING', 'active' => true]);
        $stationB->capabilities()->attach($scanCap->id, ['enabled' => true]);

        [$batchA, $dispatchA, $rawQrA] = $this->makeOrderAndDispatch('A', 'VD-ISO-A');
        [$batchB, $dispatchB, $rawQrB] = $this->makeOrderAndDispatch('B', 'VD-ISO-B');

        // Trạm A quét đơn A, trạm B quét đơn B (mô phỏng "đồng thời" — thứ tự interleave
        // không quan trọng vì mỗi job khóa theo production_batch_id riêng biệt).
        $resA = $this->postJson('/api/scanner/scan-dye-qr', ['raw_qr' => $rawQrA, 'workstation_code' => $stationA->code]);
        $resB = $this->postJson('/api/scanner/scan-dye-qr', ['raw_qr' => $rawQrB, 'workstation_code' => $stationB->code]);

        $resA->assertStatus(200);
        $resB->assertStatus(200);

        $jobIdA = $resA->json('data.job.id');
        $jobIdB = $resB->json('data.job.id');

        $this->assertNotEquals($jobIdA, $jobIdB, 'Job của 2 đơn khác nhau phải là 2 bản ghi khác nhau');

        $jobA = WeighingJob::findOrFail($jobIdA);
        $jobB = WeighingJob::findOrFail($jobIdB);

        $this->assertEquals($batchA->id, $jobA->production_batch_id);
        $this->assertEquals($batchB->id, $jobB->production_batch_id);
        $this->assertEquals($stationA->id, $jobA->assigned_workstation_id);
        $this->assertEquals($stationB->id, $jobB->assigned_workstation_id);

        // Item của job A không được xuất hiện trong job B và ngược lại.
        $itemsA = WeighingJobItem::where('weighing_job_id', $jobIdA)->pluck('id');
        $itemsB = WeighingJobItem::where('weighing_job_id', $jobIdB)->pluck('id');
        $this->assertEmpty($itemsA->intersect($itemsB), 'Item không được trùng lặp/trộn giữa 2 job của 2 trạm');

        // Cân trạm A không được ảnh hưởng bởi việc trạm B cũng vừa cân xong.
        $itemA = WeighingJobItem::where('weighing_job_id', $jobIdA)->firstOrFail();
        $itemB = WeighingJobItem::where('weighing_job_id', $jobIdB)->firstOrFail();

        $this->postJson("/api/weighing-jobs/items/{$itemA->id}/weigh", [
            'weight' => (float) $itemA->planned_weight,
            'scale_device_id' => 'SCALE_A',
            'stable' => true,
        ])->assertStatus(200);

        // Job B / item B phải còn nguyên PENDING, không bị đóng theo job A.
        $itemB->refresh();
        $this->assertEquals('PENDING', $itemB->status);
        $jobB->refresh();
        $this->assertNotEquals('COMPLETED', $jobB->status);
    }

    /**
     * PB-2 (đã sửa trước đó trong phiên) mở rộng: cache trọng lượng cân trực tiếp
     * (DeviceController) phải cô lập theo workstation_id — trạm A gửi số cân không được
     * lộ sang khi trạm B đọc.
     */
    public function test_live_scale_weight_cache_isolated_between_two_stations(): void
    {
        Cache::forget('scale_live_weight_SMALL-SCALE-A');
        Cache::forget('scale_live_weight_SMALL-SCALE-B');

        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'SMALL-SCALE-A',
            'weight' => 11.11,
            'is_stable' => true,
        ])->assertStatus(200);

        $this->postJson('/api/devices/readings', [
            'workstation_id' => 'SMALL-SCALE-B',
            'weight' => 22.22,
            'is_stable' => false,
        ])->assertStatus(200);

        $user = User::factory()->create(['username' => 'iso_reader_' . uniqid()]);

        $readA = $this->actingAs($user)->getJson('/api/devices/readings/SMALL-SCALE-A');
        $readA->assertJsonPath('weight', 11.11);
        $readA->assertJsonPath('is_stable', true);

        $readB = $this->actingAs($user)->getJson('/api/devices/readings/SMALL-SCALE-B');
        $readB->assertJsonPath('weight', 22.22);
        $readB->assertJsonPath('is_stable', false);
    }
}
