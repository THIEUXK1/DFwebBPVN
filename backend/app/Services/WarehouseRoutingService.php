<?php
// backend/app/Services/WarehouseRoutingService.php

namespace App\Services;

use App\Models\MachineDispatch;
use App\Models\RoutingDecision;
use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Log;

class WarehouseRoutingService
{
    /**
     * Calculates B24 warehouse routing and D1 area label based on Machine, Tank, Level.
     */
    public function calculateRouting(MachineDispatch $dispatch): RoutingDecision
    {
        $batch = $dispatch->batch;
        
        $machine = $batch && $batch->machine ? strtoupper(trim($batch->machine->code)) : '';
        $tank = $batch && $batch->tank ? strtoupper(trim($batch->tank->code)) : '';
        $level = $batch ? $batch->level_code : '';

        // Số thứ tự máy (vd "VD007" -> 7, "VD17" -> 17) — dùng để so sánh SỐ thay vì
        // chuỗi. VBA gốc so sánh chuỗi trực tiếp ("VD06" <= f3Val <= "VD13") vì mã máy
        // nguồn LUÔN 2 chữ số cố định nên an toàn; app.machines thật lại dùng mã 3 chữ
        // số (VD006..VD018, xác nhận qua QR thật ngày 2026-07-18: VD02/VD04). So sánh
        // chuỗi "VD007" >= "VD06" trả về FALSE (ký tự '0' < '6' ở vị trí thứ 4) dù 7>=6
        // đúng về số — khiến MỌI máy rơi vào nhánh fallback rỗng, sai hoàn toàn QR gửi
        // Color Service. Bug thật, phát hiện khi test end-to-end màn hình in tem.
        $machineNum = 0;
        if (preg_match('/^VD(\d+)$/', $machine, $m)) {
            $machineNum = (int) $m[1];
        }

        // Get active feature flags
        $flags = $this->getFeatureFlags();
        
        $b24 = null;
        $d1 = null;
        $matchedRule = 'NONE';
        $ruleVersion = 'v1.0';
        $warnings = [];
        $needsManualReview = false;

        if ($flags['b24_routing_enabled']) {
            // 1. Calculate B24 route
            if ($this->numBetween($machineNum, 6, 13) && ($tank === '1A' || $tank === '2B')) {
                $b24 = 'THUNG SAT CAO, MAY E13, MAY A11';
                $matchedRule = 'RULE_1';
            } elseif (($machineNum === 17 || $machineNum === 18) && ($tank === '1A' || $tank === '2B')) {
                $b24 = 'THUNG SAT CAO, MAY E12, MAY A11';
                $matchedRule = 'RULE_2';
            } elseif (($machineNum === 17 || $machineNum === 18) && ($tank === '3C' || $tank === '4D')) {
                if ($level == '50') {
                    $b24 = 'PHA TAY, HOA CHAT DLG';
                    $matchedRule = 'RULE_3';
                } else {
                    $b24 = 'THUNG SAT CAO, MAY E12, MAY DLG';
                    $matchedRule = 'RULE_3B';
                }
            } elseif (($this->numBetween($machineNum, 1, 5) || $this->numBetween($machineNum, 14, 16)) && ($tank === '1A' || $tank === '2B')) {
                $b24 = 'THUNG SAT THAP, MAY JIT, MAY A11';
                $matchedRule = 'RULE_4';
            } elseif ($this->numBetween($machineNum, 1, 16) && ($tank === '3C' || $tank === '4D')) {
                $b24 = 'THUNG SAT THAP, MAY JIT, MAY DLG';
                $matchedRule = 'RULE_5';
            } else {
                // Nhánh 6: rỗng
                $b24 = null;
                $matchedRule = 'FALLBACK_EMPTY';
            }

            // 2. Calculate D1 Label (Area label)
            if ($this->numBetween($machineNum, 6, 13) && ($tank === '1A' || $tank === '2B')) {
                $d1 = 'E13';
            } elseif (($machineNum === 17 || $machineNum === 18) && in_array($tank, ['1A', '2B', '3C', '4D'])) {
                $d1 = 'E12';
            } elseif ($this->numBetween($machineNum, 1, 5) && ($tank === '1A' || $tank === '2B')) {
                $d1 = 'JIT3';
            } elseif ($this->numBetween($machineNum, 1, 9) && ($tank === '3C' || $tank === '4D')) {
                $d1 = 'JIT2';
            } elseif ($this->numBetween($machineNum, 14, 16) && ($tank === '1A' || $tank === '2B')) {
                $d1 = 'JIT4';
            } elseif ($this->numBetween($machineNum, 10, 16) && ($tank === '3C' || $tank === '4D')) {
                // Xác nhận lại 2026-07-17 bằng cách đọc trực tiếp VBA gốc (Mod_printslip.bas, PrintSlip_70x100):
                // "ElseIf (f3Val >= "VD10" And f3Val <= "VD16") And (g3Val = "3C" Or g3Val = "4D") Then D1 = "JIT1""
                // Nhánh này BAO PHỦ VD10-VD16 (không phải chỉ VD10-VD13 như ghi nhận sai trước đó trong
                // b24-warehouse-routing.md). Do đó tổ hợp VD14-VD16 + 3C/4D KHÔNG có lỗ hổng D1 — luôn nhận
                // D1="JIT1" giống mọi máy khác trong dải VD10-VD16. ADR CH-BUS-012 đã đóng (RESOLVED), xem
                // decision-records.md. Giữ nguyên $d1 = '' làm giá trị mặc định an toàn cho các tổ hợp thật
                // sự không khớp nhánh nào (vd. mã máy ngoài VD01-VD18) — không phải cho VD14-16+3C/4D nữa.
                $d1 = 'JIT1';
            } else {
                $d1 = '';
            }
        }

        // 3. Determine QR Mode
        $mode = 'FB';
        if ($b24 !== null) {
            if (strpos($b24, 'MAY JIT') !== false) {
                $mode = 'PROCESS';
            } elseif (strpos($b24, 'THUNG SAT CAO') !== false) {
                $mode = 'EXTRA';
            }
        }

        // If mode is FB (default), override B24 as per VBA
        if ($mode === 'FB') {
            $b24 = 'PHU BAN-LAY LIEU COPOWER';
        }

        // Create RoutingDecision
        $decision = new RoutingDecision();
        $decision->id = \Illuminate\Support\Str::uuid();
        $decision->dispatch_id = $dispatch->id;
        $decision->mode = $mode;
        $decision->route = $b24;
        $decision->area_label = $d1;
        $decision->matched_rule = $matchedRule;
        $decision->rule_version = $ruleVersion;
        $decision->input_snapshot = [
            'machine' => $machine,
            'tank' => $tank,
            'level' => $level,
        ];
        $decision->warnings = $warnings;
        $decision->needs_manual_review = $needsManualReview;
        $decision->decided_at = now();
        $decision->save();

        return $decision;
    }

    /**
     * Helper to check if machine code is between range alphabetically.
     */
    protected function isBetween(string $val, string $min, string $max): bool
    {
        return $val >= $min && $val <= $max;
    }

    /**
     * So sánh SỐ thứ tự máy — thay cho isBetween() (so sánh chuỗi, vỡ khi mã máy thật
     * dùng 3 chữ số như "VD007" so với biên 2 chữ số "VD06"/"VD13"... trong bảng B24 gốc).
     */
    protected function numBetween(int $val, int $min, int $max): bool
    {
        return $val >= $min && $val <= $max;
    }

    /**
     * Fetch current active feature flags from database or defaults.
     */
    protected function getFeatureFlags(): array
    {
        $defaults = [
            'b24_routing_enabled' => false,
            'b24_d1_fix_enabled' => false,
            'manual_routing_review_enabled' => true,
        ];

        foreach ($defaults as $key => $default) {
            $flag = FeatureFlag::where('key', $key)->first();
            if ($flag) {
                $defaults[$key] = (bool) $flag->value;
            }
        }

        return $defaults;
    }
}
