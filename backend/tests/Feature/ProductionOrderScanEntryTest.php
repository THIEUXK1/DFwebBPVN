<?php
// backend/tests/Feature/ProductionOrderScanEntryTest.php
//
// Rà soát VBA màn hình Nhập đơn sản xuất (2026-07-18, "2.C3 grid load row lock id FB
// -192(QR).xlsm") phát hiện: (1) chưa có endpoint danh mục máy/thùng thật (frontend
// dùng mảng hardcode), (2) app.tanks chưa có tank nào cho máy VD006-VD013 -> quy tắc
// 250L trong ApproveProductionOrderService không bao giờ kích hoạt được, (3) chưa chặn
// trùng color+code như VBA Exists_ColorCode. Test này xác nhận cả 3 đã được vá.

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\ProductionBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductionOrderScanEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_machines_endpoint_lists_real_machines_including_vd_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/machines');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('VD006'));
        $this->assertTrue($codes->contains('VD013'));
    }

    /**
     * Migration 2026_07_18_020100 phải seed đủ "1A","2B","3C","4D","FB" (đúng VBA
     * formselect1) cho từng máy VD006-VD013 -> đây là dữ liệu bắt buộc để quy tắc
     * 250L trong ApproveProductionOrderService có thể kích hoạt (trước migration này,
     * KHÔNG tank nào tồn tại cho các máy này -> quy tắc chết, không test nào phát hiện
     * vì test cũ tự tạo Tank riêng, không đi qua đường seed thật).
     */
    public function test_tanks_endpoint_filtered_by_machine_returns_seeded_vba_codes(): void
    {
        $user = User::factory()->create();
        $machine = Machine::where('code', 'VD006')->firstOrFail();

        $response = $this->actingAs($user)->getJson("/api/tanks?machine_id={$machine->id}");

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code')->sort()->values()->all();
        $this->assertEquals(['1A', '2B', '3C', '4D', 'FB'], $codes);
    }

    public function test_scan_parse_endpoint_splits_scanned_string_into_order_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/production-batches/scan-parse', [
            'raw_scan' => 'RED-P123-VD010-220',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'SUCCESS',
            'data' => [
                'color' => 'RED',
                'code' => 'P123',
                'machine' => 'VD010',
                'level' => '220',
            ],
        ]);
    }

    public function test_store_rejects_duplicate_color_and_code_among_pending_new_batches(): void
    {
        $user = User::factory()->create();
        $machine = Machine::where('code', 'VD006')->firstOrFail();

        $payload = [
            'legacy_batch_id' => 'DUP-TEST-' . uniqid(),
            'color' => 'DUPCOLOR',
            'product_code' => 'DUPCODE',
            'machine_id' => $machine->id,
        ];

        $first = $this->actingAs($user)->postJson('/api/production-batches', $payload);
        $first->assertStatus(201);

        $payload['legacy_batch_id'] = 'DUP-TEST-2-' . uniqid();
        $second = $this->actingAs($user)->postJson('/api/production-batches', $payload);

        $second->assertStatus(422);
        $second->assertJsonPath('status', 'ERROR');
    }

    public function test_store_allows_same_color_and_code_once_previous_batch_is_no_longer_new(): void
    {
        $user = User::factory()->create();
        $machine = Machine::where('code', 'VD006')->firstOrFail();

        $existing = ProductionBatch::create([
            'legacy_batch_id' => 'OLD-' . uniqid(),
            'color' => 'REUSECOLOR',
            'product_code' => 'REUSECODE',
            'machine_id' => $machine->id,
            'status' => 'SENT',
        ]);

        $response = $this->actingAs($user)->postJson('/api/production-batches', [
            'legacy_batch_id' => 'NEW-' . uniqid(),
            'color' => 'REUSECOLOR',
            'product_code' => 'REUSECODE',
            'machine_id' => $machine->id,
        ]);

        $response->assertStatus(201);
        $this->assertNotEquals($existing->id, $response->json('data.id'));
    }

    /**
     * End-to-end đúng VBA btnSAVE_Click: máy trong [VD006,VD013] + tank "1A"/"2B"
     * (nay đã có thật trong DB nhờ migration seed) + level < 250 -> chặn duyệt.
     * Trước migration seed tank, test tương đương phải tự tạo Tank thủ công mới thấy
     * quy tắc hoạt động — test này xác nhận nó hoạt động với ĐÚNG dữ liệu master thật.
     */
    public function test_250l_rule_fires_using_real_seeded_tank_data(): void
    {
        $user = User::factory()->create();
        $machine = Machine::where('code', 'VD006')->firstOrFail();
        $tank = Tank::where('machine_id', $machine->id)->where('code', '1A')->firstOrFail();

        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'LVL-' . uniqid(),
            'color' => 'LVLCOLOR',
            'product_code' => 'LVLCODE',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => '100',
            'status' => 'NEW',
        ]);

        $response = $this->actingAs($user)->postJson("/api/production-batches/{$batch->id}/approve");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'MINIMUM LEVEL 250L');
    }
}
