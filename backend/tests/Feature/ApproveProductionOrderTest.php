<?php
// backend/tests/Feature/ApproveProductionOrderTest.php
//
// Kịch bản A (Production Order -> Dispatch queue) + Kịch bản E (duyệt trùng) theo
// yêu cầu "Tách riêng CHEMICAL_CALL và hoàn thiện liên kết giữa các workstation
// còn lại" (2026-07-17). Trước bản vá này KHÔNG có API/service nào tạo
// MachineDispatch từ ProductionBatch — đây là mắt xích PRODUCTION_ORDER ->
// QR_LABEL_PRINTING còn thiếu hoàn toàn.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ApproveProductionOrderTest extends TestCase
{
    use DatabaseTransactions;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = User::factory()->create(['username' => 'approve_test_op']);
    }

    private function makeBatch(string $machineCode, ?string $tankCode, ?string $level): ProductionBatch
    {
        $machine = Machine::firstOrCreate(['code' => $machineCode], ['name' => 'M ' . $machineCode]);
        $tank = $tankCode ? Tank::firstOrCreate(['code' => $tankCode], ['name' => 'T ' . $tankCode]) : null;

        return ProductionBatch::create([
            'legacy_batch_id' => 'APPROVE-' . uniqid(),
            'color' => 'GRN',
            'product_code' => 'PGRN',
            'machine_id' => $machine->id,
            'tank_id' => $tank?->id,
            'level_code' => $level,
            'status' => 'NEW',
        ]);
    }

    /** Scenario A: duyệt đơn hợp lệ -> tạo đúng 1 dispatch, trạng thái WAITING (sẵn sàng cho QR_LABEL_PRINTING). */
    public function test_approve_creates_dispatch_queue_item(): void
    {
        $batch = $this->makeBatch('VD001', '3C', '100');

        $response = $this->actingAs($this->operator)
            ->postJson("/api/production-batches/{$batch->id}/approve");

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'SUCCESS');
        $response->assertJsonPath('data.reused', false);

        $batch->refresh();
        $this->assertEquals('APPROVED', $batch->status);

        $this->assertDatabaseHas('app.machine_dispatches', [
            'batch_id' => $batch->id,
            'queue_state' => 'WAITING',
            'source_table' => 'WEB_APPROVAL',
        ]);

        $this->assertDatabaseHas('app.audit_logs', [
            'action' => 'ORDER_APPROVED_DISPATCH_CREATED',
            'entity_id' => $batch->id,
        ]);
    }

    /** Scenario E: duyệt đơn hai lần -> KHÔNG được tạo 2 dispatch. */
    public function test_approve_twice_is_idempotent_does_not_duplicate_dispatch(): void
    {
        $batch = $this->makeBatch('VD002', '3C', '100');

        $first = $this->actingAs($this->operator)->postJson("/api/production-batches/{$batch->id}/approve");
        $first->assertStatus(201);
        $first->assertJsonPath('data.reused', false);

        $second = $this->actingAs($this->operator)->postJson("/api/production-batches/{$batch->id}/approve");
        $second->assertStatus(200);
        $second->assertJsonPath('data.reused', true);

        $this->assertEquals(1, MachineDispatch::where('batch_id', $batch->id)->count());
    }

    /**
     * Quy tắc "MINIMUM LEVEL 250L" (VD006-VD013 + tank 1A/2B + level < 250) đã bị
     * BỎ theo yêu cầu người dùng 2026-07-28 — mọi mức nước hợp lệ trong dropdown
     * (50/100/250/450) đều được chấp nhận, không còn phân biệt máy/thùng.
     */
    public function test_approve_allows_low_level_on_previously_restricted_machine_tank(): void
    {
        $batch = $this->makeBatch('VD007', '1A', '100');

        $response = $this->actingAs($this->operator)
            ->postJson("/api/production-batches/{$batch->id}/approve");

        $response->assertStatus(201);
        $batch->refresh();
        $this->assertEquals('APPROVED', $batch->status);
    }

    public function test_approve_allows_when_min_level_250_satisfied(): void
    {
        $batch = $this->makeBatch('VD008', '2B', '250');

        $response = $this->actingAs($this->operator)
            ->postJson("/api/production-batches/{$batch->id}/approve");

        $response->assertStatus(201);
        $batch->refresh();
        $this->assertEquals('APPROVED', $batch->status);
    }

    /**
     * BUG NGHIÊM TRỌNG phát hiện 2026-07-19: raw_qr_dye/raw_qr_chemical đã lưu đúng trên
     * batch lúc quét (Production Order) nhưng KHÔNG BAO GIỜ được copy sang machine_dispatches
     * khi duyệt — khiến QR THẬT gửi cho Color Service (QrPayloadService::buildDyePayload/
     * buildChemPayload đọc từ $dispatch, không phải $batch) thiếu toàn bộ dữ liệu thuốc
     * nhuộm/hóa chất. Test này khóa hành vi đúng: duyệt xong phải copy nguyên vẹn.
     */
    public function test_approve_copies_raw_qr_dye_and_chemical_to_dispatch(): void
    {
        $batch = $this->makeBatch('VD003', '1A', '450');
        $batch->raw_qr_dye = '1A-Y1104-111.15-2B-R2128-33.75';
        $batch->raw_qr_chemical = '1A-AC02-3600-2B-AC06-3600';
        $batch->save();

        $response = $this->actingAs($this->operator)
            ->postJson("/api/production-batches/{$batch->id}/approve");

        $response->assertStatus(201);

        $this->assertDatabaseHas('app.machine_dispatches', [
            'batch_id' => $batch->id,
            'raw_qr_dye' => '1A-Y1104-111.15-2B-R2128-33.75',
            'raw_qr_chemical' => '1A-AC02-3600-2B-AC06-3600',
        ]);
    }

}
