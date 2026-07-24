<?php
// backend/tests/Feature/WeighItemStabilityGateTest.php
//
// p0-c-scale-algorithm.md Mục A.4: WeighingJobController::weighItem trước đây validate
// 'stable' là boolean hợp lệ nhưng KHÔNG dùng nó để chặn lưu — client gọi thẳng API với
// stable:false vẫn được lưu bình thường. VBA chặn cứng qua StableFilter. Test này xác
// nhận backend giờ chặn đúng khi stable=false, và vẫn cho lưu bình thường khi stable=true.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Material;
use App\Models\ScaleMeasurement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WeighItemStabilityGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeWeighingItem(): WeighingJobItem
    {
        $user = User::factory()->create(['username' => 'op_stable_' . uniqid()]);
        $machine = Machine::firstOrCreate(['code' => 'VD-STABLE-TEST'], ['name' => 'x']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'STB' . time() . rand(1, 999),
            'color' => 'C', 'product_code' => 'P',
            'machine_id' => $machine->id, 'status' => 'APPROVED',
        ]);
        $job = WeighingJob::create([
            'production_batch_id' => $batch->id,
            'job_type' => 'DYE',
            'workstation_type' => 'SMALL_SCALE',
            'status' => 'IN_PROGRESS',
        ]);
        Material::firstOrCreate(['code' => 'DYE-001'], ['name' => 'Test Dye', 'type' => 'DYE']);
        $item = WeighingJobItem::create([
            'weighing_job_id' => $job->id,
            'material_code' => 'DYE-001',
            'planned_weight' => 100.0,
            'tolerance_minus' => 1.0,
            'tolerance_plus' => 1.0,
            'sequence_no' => 1,
            'status' => 'PENDING',
        ]);

        $this->actingAsUser = $user;
        return $item;
    }

    private $actingAsUser;

    public function test_weigh_item_rejected_when_stable_false()
    {
        $item = $this->makeWeighingItem();

        $response = $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 100.0,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => false,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'NOT_STABLE');
        $this->assertEquals('PENDING', WeighingJobItem::find($item->id)->status);
    }

    public function test_weigh_item_accepted_when_stable_true_and_in_range()
    {
        $item = $this->makeWeighingItem();

        $response = $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 100.0,
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('COMPLETED', WeighingJobItem::find($item->id)->status);
    }

    /**
     * CH-BUS-006 (xác nhận 2026-07-18): "chắc chắn phải trừ bì, cốc/khay/thùng đặt lên
     * trước coi như là bì" — đúng VBA Delta_Begin/AutoFlow_OnWeight. Frontend tự trừ bì
     * và gửi 'weight' đã là NET; 'tare_weight'/'gross_weight' chỉ để audit minh bạch,
     * không ảnh hưởng logic so dung sai (vẫn so đúng 'weight' như trước).
     */
    public function test_weigh_item_stores_tare_and_gross_weight_for_audit()
    {
        $item = $this->makeWeighingItem();

        $response = $this->actingAs($this->actingAsUser)->postJson("/api/weighing-jobs/items/{$item->id}/weigh", [
            'weight' => 100.0,      // net = đã trừ bì, dùng để so dung sai (đạt: 99-101)
            'gross_weight' => 105.0, // cân gộp (cốc + vật tư)
            'tare_weight' => 5.0,    // bì (cốc rỗng)
            'scale_device_id' => 'MOCK_SCALE',
            'stable' => true,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('COMPLETED', WeighingJobItem::find($item->id)->status);

        $measurement = ScaleMeasurement::where('weighing_job_item_id', $item->id)->first();
        $this->assertNotNull($measurement);
        $this->assertEquals(100.0, (float) $measurement->weight);
        $this->assertEquals(5.0, (float) $measurement->tare_weight);
        $this->assertEquals(105.0, (float) $measurement->gross_weight);
    }
}
