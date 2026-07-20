<?php
// backend/database/migrations/2026_07_17_000007_add_area_label_to_routing_decisions_table.php
//
// Bổ sung cột lưu nhãn khu vực máy JIT (D1 trong VBA gốc, Mod_printslip.PrintSlip_70x100)
// vào app.routing_decisions — trước đây WarehouseRoutingService tính ra $d1 nhưng không
// lưu, phát hiện khi đối chiếu chéo code với b24-warehouse-routing.md ngày 2026-07-17.
// Additive-only, không sửa cột hiện có, khớp nguyên tắc migration-plan.md Mục 4.2.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routing_decisions', function (Blueprint $table) {
            $table->string('area_label', 20)->nullable()->after('route');
        });
    }

    public function down(): void
    {
        Schema::table('routing_decisions', function (Blueprint $table) {
            $table->dropColumn('area_label');
        });
    }
};
