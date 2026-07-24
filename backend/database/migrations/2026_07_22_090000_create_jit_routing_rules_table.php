<?php
// backend/database/migrations/2026_07_22_090000_create_jit_routing_rules_table.php
//
// Port bảng quy tắc định tuyến JIT từ VBA (Mod_printslip.PrintSlip_70x100, dòng
// 1736-1766 file "3.DF028...jit qr sending...xlsm") sang dữ liệu có thể audit/override,
// thay vì chỉ hard-code trong WarehouseRoutingService::calculateRouting(). Quy tắc đã
// được đối chiếu 100% khớp với dữ liệu thật trong BPDB.DyeMachineRelationships (Color
// Service) — xem colorservice-trace-report. routing_system_id chỉ điền cho các dòng
// JIT (đã xác nhận qua BPDB.SUP_Tasks.DisSystemId="SCCM" thật) — để trống cho E12/E13
// vì CHƯA có bằng chứng con đường tích hợp BPDB nào cho 2 tuyến đó.
//
// PORTED từ DFwed2 (Postgres + schema `app.`) sang DFwed (SQLite, không có schema
// prefix): bỏ `app.` prefix, bỏ default gen_random_uuid() (không tồn tại trên SQLite —
// id được sinh trong PHP qua JitRoutingRule::boot()/creating(), giống MachineDispatch/
// ProductionBatch), timestampTz() giữ nguyên (Laravel map về datetime bình thường trên
// SQLite). Toán học seed giữ NGUYÊN VẸN, không sửa — đã được đối chiếu độc lập với
// WarehouseRoutingService::calculateRouting() ở cả 2 dự án.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jit_routing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('machine_code', 20);
            $table->string('tank_code', 10);
            $table->integer('water_level')->nullable();
            $table->string('jit_queue_code', 20)->nullable();
            $table->string('b24_route', 100)->nullable();
            $table->string('qr_mode', 20)->default('FB');
            $table->string('routing_system_id', 64)->nullable();
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->string('source_type', 30)->default('VBA_PORT');
            $table->string('source_reference', 255)->nullable();
            $table->uuid('overridden_by_user_id')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->foreign('overridden_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Ưu tiên khớp dòng có water_level cụ thể trước dòng tổng quát (level NULL) —
        // dùng cho trường hợp đặc biệt VD17/18+3C/4D+level=50 -> "PHA TAY, HOA CHAT DLG".
        // SQLite hỗ trợ partial index (đã dùng thành công cho uq_channel_active_order
        // trên chemical_call_requests trong wave2 migration) — dùng cùng cú pháp.
        DB::statement("
            CREATE UNIQUE INDEX uq_jit_routing_rule_active
            ON jit_routing_rules (machine_code, tank_code, COALESCE(water_level, -1))
            WHERE is_active = true
        ");

        $this->seedFromVbaRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('jit_routing_rules');
    }

    /**
     * Sinh đúng 18 máy (VD001-VD018) x 4 tank (1A/2B/3C/4D) = 72 dòng, tính toán bằng
     * CHÍNH logic đã xác minh trong WarehouseRoutingService::calculateRouting() (không
     * chép tay để tránh sai lệch giữa 2 nơi).
     */
    private function seedFromVbaRules(): void
    {
        // SystemId thật của hệ "SCCM" trong BPDB, xác nhận qua SUP_Tasks.DisSystemId
        // (colorservice-trace-report, mục 04) — dùng cho mọi tuyến JIT1-4.
        $sccmSystemId = '2ca15dd4-e70e-490f-a395-d17f7eb4779b';

        $now = now();
        $rows = [];

        for ($num = 1; $num <= 18; $num++) {
            $machineCode = 'VD' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);

            foreach (['1A', '2B', '3C', '4D'] as $tank) {
                [$b24, $d1] = $this->computeRoute($num, $tank, null);
                $mode = $this->computeMode($b24);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'machine_code' => $machineCode,
                    'tank_code' => $tank,
                    'water_level' => null,
                    'jit_queue_code' => $d1 ?: null,
                    'b24_route' => $b24,
                    'qr_mode' => $mode,
                    'routing_system_id' => $d1 ? $sccmSystemId : null,
                    'priority' => 100,
                    'is_active' => true,
                    'source_type' => 'VBA_PORT',
                    'source_reference' => 'Mod_printslip.PrintSlip_70x100 (3.DF028...jit qr sending...xlsm), dòng 1736-1766',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                // Trường hợp đặc biệt: VD17/VD18 + 3C/4D + level=50 -> B24 khác hẳn
                // ("PHA TAY, HOA CHAT DLG" thay vì "THUNG SAT CAO, MAY E12, MAY DLG").
                // Không ảnh hưởng D1/JIT vì D1 luôn = "E12" bất kể level ở dải máy này.
                if (($num === 17 || $num === 18) && in_array($tank, ['3C', '4D'], true)) {
                    [$b24Level, $d1Level] = $this->computeRoute($num, $tank, 50);
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'machine_code' => $machineCode,
                        'tank_code' => $tank,
                        'water_level' => 50,
                        'jit_queue_code' => $d1Level ?: null,
                        'b24_route' => $b24Level,
                        'qr_mode' => $this->computeMode($b24Level),
                        'routing_system_id' => null,
                        'priority' => 10, // ưu tiên hơn dòng tổng quát ở trên
                        'is_active' => true,
                        'source_type' => 'VBA_PORT',
                        'source_reference' => 'Mod_printslip.PrintSlip_70x100, dòng 1571-1575 (nhánh level=50 "PHA TAY")',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('jit_routing_rules')->insert($chunk);
        }
    }

    /** Bản sao chính xác logic B24/D1 trong WarehouseRoutingService để seed dữ liệu nhất quán. */
    private function computeRoute(int $machineNum, string $tank, ?int $level): array
    {
        $b24 = null;
        if ($this->between($machineNum, 6, 13) && in_array($tank, ['1A', '2B'], true)) {
            $b24 = 'THUNG SAT CAO, MAY E13, MAY A11';
        } elseif (in_array($machineNum, [17, 18], true) && in_array($tank, ['1A', '2B'], true)) {
            $b24 = 'THUNG SAT CAO, MAY E12, MAY A11';
        } elseif (in_array($machineNum, [17, 18], true) && in_array($tank, ['3C', '4D'], true)) {
            $b24 = ($level === 50) ? 'PHA TAY, HOA CHAT DLG' : 'THUNG SAT CAO, MAY E12, MAY DLG';
        } elseif (($this->between($machineNum, 1, 5) || $this->between($machineNum, 14, 16)) && in_array($tank, ['1A', '2B'], true)) {
            $b24 = 'THUNG SAT THAP, MAY JIT, MAY A11';
        } elseif ($this->between($machineNum, 1, 16) && in_array($tank, ['3C', '4D'], true)) {
            $b24 = 'THUNG SAT THAP, MAY JIT, MAY DLG';
        }

        $d1 = '';
        if ($this->between($machineNum, 6, 13) && in_array($tank, ['1A', '2B'], true)) {
            $d1 = 'E13';
        } elseif (in_array($machineNum, [17, 18], true) && in_array($tank, ['1A', '2B', '3C', '4D'], true)) {
            $d1 = 'E12';
        } elseif ($this->between($machineNum, 1, 5) && in_array($tank, ['1A', '2B'], true)) {
            $d1 = 'JIT3';
        } elseif ($this->between($machineNum, 1, 9) && in_array($tank, ['3C', '4D'], true)) {
            $d1 = 'JIT2';
        } elseif ($this->between($machineNum, 14, 16) && in_array($tank, ['1A', '2B'], true)) {
            $d1 = 'JIT4';
        } elseif ($this->between($machineNum, 10, 16) && in_array($tank, ['3C', '4D'], true)) {
            $d1 = 'JIT1';
        }

        return [$b24, $d1];
    }

    private function computeMode(?string $b24): string
    {
        if ($b24 === null) {
            return 'FB';
        }
        if (str_contains($b24, 'MAY JIT')) {
            return 'PROCESS';
        }
        if (str_contains($b24, 'THUNG SAT CAO')) {
            return 'EXTRA';
        }
        return 'FB';
    }

    private function between(int $val, int $min, int $max): bool
    {
        return $val >= $min && $val <= $max;
    }
};
