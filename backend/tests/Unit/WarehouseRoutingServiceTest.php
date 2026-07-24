<?php
// backend/tests/Unit/WarehouseRoutingServiceTest.php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WarehouseRoutingService;
use App\Models\MachineDispatch;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WarehouseRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $routingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingService = app(WarehouseRoutingService::class);
        
        // Setup default feature flags
        FeatureFlag::updateOrCreate(
            ['key' => 'b24_routing_enabled'],
            ['value' => true, 'description' => 'Enable B24 routing']
        );
        FeatureFlag::updateOrCreate(
            ['key' => 'b24_d1_fix_enabled'],
            ['value' => false, 'description' => 'Fix gap']
        );
        FeatureFlag::updateOrCreate(
            ['key' => 'manual_routing_review_enabled'],
            ['value' => true, 'description' => 'Manual review']
        );
    }

    /**
     * Test B24 Routing Decision Table (Section 2 of b24-warehouse-routing.md).
     *
     * Bug thật phát hiện 2026-07-18 (test end-to-end màn hình in tem): mã máy dùng
     * ở đây trước là 2 chữ số ("VD10","VD17"...) — KHÔNG khớp định dạng thật của
     * app.machines (3 chữ số, "VD010","VD017"..., xác nhận qua QR MES thật). Vì
     * WarehouseRoutingService so sánh CHUỖI ("VD007" >= "VD06" trả về FALSE dù 7>=6
     * đúng về số), test với mã 2 chữ số này KHÔNG hề phát hiện được bug — mọi máy
     * thật (3 chữ số) đều rơi vào fallback rỗng sai hoàn toàn. Đổi sang mã 3 chữ số
     * đúng định dạng thật để test này thực sự bảo vệ được logic.
     */
    public function test_b24_routing_decision_table()
    {
        // Case 1: VD010, 1A (RULE_1) -> B24 = "THUNG SAT CAO, MAY E13, MAY A11" -> mode = EXTRA -> D1 = "E13"
        $dispatch1 = $this->createMockDispatch('VD010', '1A', '220');
        $decision1 = $this->routingService->calculateRouting($dispatch1);

        $this->assertEquals('EXTRA', $decision1->mode);
        $this->assertEquals('THUNG SAT CAO, MAY E13, MAY A11', $decision1->route);
        $this->assertEquals('E13', $decision1->area_label);
        $this->assertEquals('RULE_1', $decision1->matched_rule);

        // Case 2: VD018, 2B (RULE_2) -> B24 = "THUNG SAT CAO, MAY E12, MAY A11" -> mode = EXTRA
        $dispatch2 = $this->createMockDispatch('VD018', '2B', '220');
        $decision2 = $this->routingService->calculateRouting($dispatch2);

        $this->assertEquals('EXTRA', $decision2->mode);
        $this->assertEquals('THUNG SAT CAO, MAY E12, MAY A11', $decision2->route);
        $this->assertEquals('RULE_2', $decision2->matched_rule);

        // Case 3: VD017, 3C, level 50 (RULE_3) -> B24 = "PHA TAY, HOA CHAT DLG" -> mode = FB
        $dispatch3 = $this->createMockDispatch('VD017', '3C', '50');
        $decision3 = $this->routingService->calculateRouting($dispatch3);

        $this->assertEquals('FB', $decision3->mode);
        $this->assertEquals('PHU BAN-LAY LIEU COPOWER', $decision3->route);
        $this->assertEquals('RULE_3', $decision3->matched_rule);

        // Case 4: VD017, 4D, level 100 (RULE_3B) -> B24 = "THUNG SAT CAO, MAY E12, MAY DLG" -> mode = EXTRA
        $dispatch4 = $this->createMockDispatch('VD017', '4D', '100');
        $decision4 = $this->routingService->calculateRouting($dispatch4);

        $this->assertEquals('EXTRA', $decision4->mode);
        $this->assertEquals('THUNG SAT CAO, MAY E12, MAY DLG', $decision4->route);
        $this->assertEquals('RULE_3B', $decision4->matched_rule);

        // Case 5: VD003, 1A (RULE_4) -> B24 = "THUNG SAT THAP, MAY JIT, MAY A11" -> mode = PROCESS
        $dispatch5 = $this->createMockDispatch('VD003', '1A', '220');
        $decision5 = $this->routingService->calculateRouting($dispatch5);

        $this->assertEquals('PROCESS', $decision5->mode);
        $this->assertEquals('THUNG SAT THAP, MAY JIT, MAY A11', $decision5->route);
        $this->assertEquals('RULE_4', $decision5->matched_rule);

        // Case 6: VD007, 3C, level 50 (RULE_5) -> B24 = "THUNG SAT THAP, MAY JIT, MAY DLG" -> mode = PROCESS
        $dispatch6 = $this->createMockDispatch('VD007', '3C', '50');
        $decision6 = $this->routingService->calculateRouting($dispatch6);

        $this->assertEquals('PROCESS', $decision6->mode);
        $this->assertEquals('THUNG SAT THAP, MAY JIT, MAY DLG', $decision6->route);
        $this->assertEquals('RULE_5', $decision6->matched_rule);
    }

    /**
     * VD14-VD16 + 3C/4D: đã xác nhận lại 2026-07-17 bằng cách đọc trực tiếp VBA gốc
     * (Mod_printslip.bas) — KHÔNG có lỗ hổng D1 như tài liệu audit trước đây ghi nhận
     * sai. Nhánh cuối cùng của VBA (D1="JIT1") bao phủ VD10-VD16, không phải chỉ
     * VD10-VD13. ADR CH-BUS-012 đã đóng (RESOLVED). Test này xác nhận D1 luôn được
     * gán đúng "JIT1" cho tổ hợp VD14-VD16 + 3C/4D, không cần manual review.
     */
    public function test_vd14_to_vd16_with_tank_3c_4d_resolves_to_jit1_no_gap()
    {
        foreach (['VD014', 'VD015', 'VD016'] as $machineCode) {
            foreach (['3C', '4D'] as $tankCode) {
                $dispatch = $this->createMockDispatch($machineCode, $tankCode, '200');
                $decision = $this->routingService->calculateRouting($dispatch);

                $this->assertFalse($decision->needs_manual_review, "$machineCode+$tankCode should not need manual review");
                $this->assertEquals('THUNG SAT THAP, MAY JIT, MAY DLG', $decision->route, "$machineCode+$tankCode route mismatch");
                $this->assertEquals('PROCESS', $decision->mode, "$machineCode+$tankCode mode mismatch");
                $this->assertEquals('JIT1', $decision->area_label, "$machineCode+$tankCode area_label mismatch");
                $this->assertEmpty($decision->warnings, "$machineCode+$tankCode should have no warnings");
            }
        }
    }

    protected function createMockDispatch($machineCode, $tankCode, $levelCode)
    {
        $machine = Machine::firstOrCreate(['code' => $machineCode], ['name' => 'Machine ' . $machineCode]);
        $tank = Tank::firstOrCreate(['machine_id' => $machine->id, 'code' => $tankCode], ['name' => 'Tank ' . $tankCode]);
        
        $batch = ProductionBatch::create([
            'legacy_batch_id' => 'B' . time() . rand(1, 99),
            'color' => 'RED',
            'product_code' => 'P123',
            'machine_id' => $machine->id,
            'tank_id' => $tank->id,
            'level_code' => $levelCode,
            'status' => 'APPROVED',
        ]);

        return MachineDispatch::create([
            'batch_id' => $batch->id,
            'queue_state' => 'WAITING',
            'source_table' => 'tbl_ToSend2',
            'legacy_id' => rand(100, 999),
            'legacy_row_no' => rand(1000, 9999),
        ]);
    }
}
