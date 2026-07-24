<?php
// backend/tests/Feature/ConfirmDispatchTest.php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MachineDispatch;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\User;
use App\Models\FeatureFlag;
use App\Models\PrintJob;
use App\Models\QrPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ConfirmDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'username' => 'admin_cd',
            'display_name' => 'Admin'
        ]);

        // Setup default feature flags
        FeatureFlag::updateOrCreate(
            ['key' => 'b24_routing_enabled'],
            ['value' => true, 'description' => 'Enable B24 routing']
        );
        FeatureFlag::updateOrCreate(
            ['key' => 'b24_d1_fix_enabled'],
            ['value' => true, 'description' => 'Fix gap']
        );
        FeatureFlag::updateOrCreate(
            ['key' => 'manual_routing_review_enabled'],
            ['value' => true, 'description' => 'Manual review']
        );
    }

    /**
     * Test confirming a dispatch row.
     */
    public function test_confirm_dispatch_flow()
    {
        $machine = Machine::create(['code' => 'VD10', 'name' => 'Machine VD10']);
        $tank = Tank::create(['code' => '1A', 'name' => 'Tank 1A']);
        
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B' . time() . rand(1, 99),
            'color' => 'RED',
            'product_code' => 'P123',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => '220',
            'status' => 'APPROVED',
        ]);

        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
        ]);

        $idempotencyKey = 'key_' . Str::uuid();

        // 1. Confirm first time
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/machine-dispatches/{$dispatch->id}/confirm", [
                'idempotency_key' => $idempotencyKey,
                'workstation_id' => 'QR_LABEL_PRINTING_1',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'SUCCESS');
        $response->assertJsonPath('data.status', 'CONFIRMED');
        $response->assertJsonPath('data.reused', false);

        // Verify database updates
        $this->assertDatabaseHas('app.machine_dispatches', [
            'id' => $dispatch->id,
            'queue_state' => 'CONFIRMED',
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->assertDatabaseHas('app.qr_payloads', [
            'dispatch_id' => $dispatch->id,
            'payload_type' => 'DYE',
        ]);

        $this->assertDatabaseHas('app.print_jobs', [
            'dispatch_id' => $dispatch->id,
            'status' => 'PENDING',
        ]);

        // 2. Confirm second time (should trigger idempotency and return reused=true)
        $response2 = $this->actingAs($this->adminUser)
            ->postJson("/api/machine-dispatches/{$dispatch->id}/confirm", [
                'idempotency_key' => $idempotencyKey,
                'workstation_id' => 'QR_LABEL_PRINTING_1',
            ]);

        $response2->assertStatus(200);
        $response2->assertJsonPath('data.reused', true);

        // 3. QR payload phải đúng định dạng VBA gốc (CLAUDE.md C-04), không phải chuỗi tự chế
        $dyePayload = QrPayload::where('dispatch_id', $dispatch->id)->where('payload_type', 'DYE')->first();
        $this->assertEquals('#RED-P123-VD10-220-', $dyePayload->raw_payload);
    }

    /**
     * Regression test cho race condition đã phát hiện khi review code Phase E (2026-07-17):
     * gọi confirm() 2 lần với 2 idempotency_key KHÁC NHAU cho cùng 1 dispatch (mô phỏng 2
     * người xác nhận cùng dòng, state-machines.md Mục 3, test 2.2). Trước khi sửa, code chỉ
     * kiểm tra "đã confirm chưa" TRƯỚC khi khóa dòng — lần gọi thứ 2 (dù tuần tự) vẫn đi qua
     * nhánh tạo mới toàn bộ QrPayload/PrintJob/DispatchEvent lần nữa vì không re-check SAU khi
     * khóa. Sau khi sửa: lần gọi thứ 2 phải nhận `reused=true`, KHÔNG tạo thêm payload/print job.
     */
    public function test_second_confirm_with_different_idempotency_key_does_not_duplicate()
    {
        $machine = Machine::create(['code' => 'VD10', 'name' => 'Machine VD10']);
        $tank = Tank::create(['code' => '1A', 'name' => 'Tank 1A']);
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B' . time() . rand(1, 99),
            'color' => 'RED', 'product_code' => 'P123',
            'machine_id' => $machine->id, 'tank_id' => $tank->id,
            'level_code' => '220', 'status' => 'APPROVED',
        ]);
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id, 'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2', 'legacy_id' => rand(100, 999), 'legacy_row_no' => rand(1000, 9999),
        ]);

        $service = app(\App\Services\ConfirmDispatchService::class);
        $result1 = $service->confirm($dispatch->id, 'key_first_' . Str::uuid());
        $this->assertFalse($result1['reused']);

        $result2 = $service->confirm($dispatch->id, 'key_second_' . Str::uuid());
        $this->assertTrue($result2['reused'], 'Lần confirm thứ 2 (idempotency_key khác) phải nhận reused=true, không tạo trùng dữ liệu');
        $this->assertEquals($result1['print_job_id'], $result2['print_job_id']);

        $this->assertEquals(3, QrPayload::where('dispatch_id', $dispatch->id)->count(), 'Không được tạo thêm QrPayload ở lần confirm thứ 2');
        $this->assertEquals(1, PrintJob::where('dispatch_id', $dispatch->id)->count(), 'Không được tạo thêm PrintJob ở lần confirm thứ 2');
    }

    /**
     * Lịch sử in tem (PrintStation.vue) — đơn đã CONFIRMED phải rời khỏi hàng chờ
     * (/machine-dispatches) NHƯNG vẫn phải xem lại được qua /machine-dispatches/history,
     * kèm đúng trạng thái PrintJob thật (PENDING/PRINTED/FAILED).
     */
    public function test_history_endpoint_lists_confirmed_dispatch_with_print_job_status()
    {
        $machine = Machine::firstOrCreate(['code' => 'VD-HIST-TEST'], ['name' => 'Machine VD-HIST-TEST']);
        $tank = Tank::firstOrCreate(['machine_id' => $machine->id, 'code' => '1A'], ['name' => 'Tank 1A']);

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B' . time() . rand(1, 99),
            'color' => 'HISTCOLOR', 'product_code' => 'HISTCODE',
            'machine_id' => $machine->id, 'tank_id' => $tank->id,
            'level_code' => '220', 'status' => 'APPROVED',
        ]);
        $dispatch = MachineDispatch::create([
            'batch_id' => $batch->id, 'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2', 'legacy_id' => rand(100, 999), 'legacy_row_no' => rand(1000, 9999),
        ]);

        // Chưa confirm -> chưa xuất hiện trong lịch sử, vẫn còn trong hàng chờ đang hoạt động.
        $beforeHistory = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history');
        $beforeHistory->assertStatus(200);
        $this->assertFalse(collect($beforeHistory->json('data'))->contains('id', $dispatch->id));

        $beforeQueue = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches');
        $this->assertTrue(collect($beforeQueue->json())->contains('id', $dispatch->id));

        app(\App\Services\ConfirmDispatchService::class)->confirm($dispatch->id, 'key_hist_' . Str::uuid());

        // Sau confirm -> rời khỏi hàng chờ, xuất hiện đúng trong lịch sử kèm print_job.
        $afterQueue = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches');
        $this->assertFalse(collect($afterQueue->json())->contains('id', $dispatch->id), 'Đơn đã CONFIRMED không được còn nằm trong hàng chờ đang hoạt động');

        $afterHistory = $this->actingAs($this->adminUser)->getJson('/api/machine-dispatches/history');
        $afterHistory->assertStatus(200);
        $row = collect($afterHistory->json('data'))->firstWhere('id', $dispatch->id);
        $this->assertNotNull($row, 'Đơn đã CONFIRMED phải xuất hiện trong lịch sử in tem');
        $this->assertEquals('HISTCOLOR', $row['batch']['color']);
        $this->assertCount(1, $row['print_jobs'], 'Tier B: đúng 1 print job cho lần in đầu');
        $this->assertEquals('PENDING', $row['print_jobs'][0]['status']);
        $this->assertCount(0, $row['print_jobs'][0]['attempts'], 'Chưa Agent ack thì chưa có print_attempts (tier C)');
    }
}
