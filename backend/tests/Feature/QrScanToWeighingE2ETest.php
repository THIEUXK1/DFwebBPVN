<?php
// backend/tests/Feature/QrScanToWeighingE2ETest.php
//
// Scenario A/B rút gọn theo yêu cầu "Tách riêng CHEMICAL_CALL và hoàn thiện liên kết
// giữa các workstation còn lại" (2026-07-17): PRODUCTION_ORDER -> QR_LABEL_PRINTING
// (QrPayloadService sinh QR đúng VBA gốc) -> quét QR THẬT tại trạm cân -> WeighingJob
// -> ghi correlation RECORD_A<->RECORD_B (app.correlation_links).
//
// Trước bản vá này: QR do QrPayloadService sinh ra không được ScannerController::scan()
// hiểu (chỉ nhận "DF:ORDER:<uuid>"), và app.correlation_links tồn tại trong schema nhưng
// chưa từng được ghi bởi bất kỳ code nào. Test này chứng minh cả 2 việc đã được nối dây.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\Material;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Models\WeighingJob;
use App\Models\CorrelationLink;
use App\Models\Workstation;
use App\Services\QrPayloadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class QrScanToWeighingE2ETest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'WorkstationsSeeder']);
    }

    public function test_real_qr_from_label_printing_is_scanned_at_scale_and_correlates_to_dispatch(): void
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'e2e_scan_' . uniqid();
        $user->password_hash = password_hash('pw', PASSWORD_BCRYPT);
        $user->save();
        Sanctum::actingAs($user);

        $machine = Machine::create(['code' => 'VDE2E', 'name' => 'Machine E2E', 'is_active' => true]);
        $tank = Tank::create(['code' => '1A', 'name' => 'Tank E2E']);

        $material = Material::create(['code' => 'DYE-E2E-01', 'name' => 'Dye E2E', 'type' => 'DYE', 'is_active' => true]);
        $recipe = Recipe::create(['color_code' => 'E2ECOLOR', 'product_code' => 'E2ECODE', 'description' => 'E2E']);
        $version = RecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'ACTIVE']);
        RecipeMaterial::create([
            'recipe_version_id' => $version->id,
            'material_code' => $material->code,
            'concentration' => 1.0,
            'process_code' => 'HS',
        ]);

        // 1. PRODUCTION_ORDER: đơn đã duyệt (bỏ qua approve() ở đây để test tập trung vào
        // đoạn QR_LABEL_PRINTING -> SMALL_SCALE; approve() đã có test riêng ở
        // ApproveProductionOrderTest).
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'E2E-' . uniqid(),
            'color' => 'E2ECOLOR',
            'product_code' => 'E2ECODE',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => '220',
            'cloth_weight' => 200.0,
            'status' => 'APPROVED',
        ]);

        // 2. QR_LABEL_PRINTING: dispatch đã confirm, sinh QR THẬT qua QrPayloadService
        // (đúng định dạng VBA — không phải DF:ORDER:<uuid>).
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'CONFIRMED',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
            'raw_qr_dye' => 'RACK1-Y1008A-1.500',
        ]);

        $rawQr = app(QrPayloadService::class)->buildDyePayload($dispatch, $batch);
        $this->assertStringStartsWith('#E2ECOLOR-E2ECODE-VDE2E-220-', $rawQr);

        // 3. Quét QR THẬT tại trạm cân (WS-DYE, seeded DYE_WEIGHING) — không dùng
        // DF:ORDER:<uuid> giả nữa.
        $wsDye = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $response = $this->postJson('/api/scanner/scan-dye-qr', [
            'raw_qr' => $rawQr,
            'workstation_code' => $wsDye->code,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');

        $jobId = $response->json('data.job.id');
        $this->assertNotNull($jobId);

        $this->assertDatabaseHas('app.weighing_jobs', [
            'id' => $jobId,
            'production_batch_id' => $batch->id,
            'job_type' => 'DYE',
        ]);

        // 4. Correlation RECORD_A (dispatch) <-> RECORD_B (weighing job) phải được ghi,
        // khớp theo color+code+machine (không phải timestamp).
        $link = CorrelationLink::where('dispatch_id', $dispatch->id)
            ->where('weighing_job_id', $jobId)
            ->first();

        $this->assertNotNull($link, 'correlation_links phải có bản ghi nối dispatch <-> weighing job');
        $this->assertEquals('DETERMINISTIC_COMPOSITE', $link->match_method);
        $this->assertEquals('LINKED', $link->status);
        $this->assertEquals('E2ECOLOR', $link->matched_on['color']);
    }

    /** Quét lại đúng QR đó lần 2 -> không tạo thêm correlation_links trùng lặp. */
    public function test_scanning_same_qr_twice_does_not_duplicate_correlation_link(): void
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'e2e_scan2_' . uniqid();
        $user->password_hash = password_hash('pw', PASSWORD_BCRYPT);
        $user->save();
        Sanctum::actingAs($user);

        $machine = Machine::create(['code' => 'VDE2E2', 'name' => 'Machine E2E2', 'is_active' => true]);
        $material = Material::create(['code' => 'DYE-E2E-02', 'name' => 'Dye E2E2', 'type' => 'DYE', 'is_active' => true]);
        $recipe = Recipe::create(['color_code' => 'E2ECOLOR2', 'product_code' => 'E2ECODE2', 'description' => 'E2E2']);
        $version = RecipeVersion::create(['recipe_id' => $recipe->id, 'version' => 1, 'status' => 'ACTIVE']);
        RecipeMaterial::create([
            'recipe_version_id' => $version->id,
            'material_code' => $material->code,
            'concentration' => 1.0,
            'process_code' => 'HS',
        ]);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'E2E2-' . uniqid(),
            'color' => 'E2ECOLOR2',
            'product_code' => 'E2ECODE2',
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
        $wsDye = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $this->postJson('/api/scanner/scan-dye-qr', ['raw_qr' => $rawQr, 'workstation_code' => $wsDye->code])
            ->assertStatus(200);
        $this->postJson('/api/scanner/scan-dye-qr', ['raw_qr' => $rawQr, 'workstation_code' => $wsDye->code])
            ->assertStatus(200);

        $this->assertEquals(1, CorrelationLink::where('dispatch_id', $dispatch->id)->count());
    }
}
