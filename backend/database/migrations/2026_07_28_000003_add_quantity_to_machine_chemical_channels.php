<?php
// backend/database/migrations/2026_07_28_000003_add_quantity_to_machine_chemical_channels.php
//
// Phát hiện 2026-07-28 khi đối chiếu dữ liệu thật VD006: "quantity" trong QR Báo phát AC
// KHÔNG cố định theo công thức hóa chất (chemical_formula_groups.quantity=150 cho cả 2
// thùng VD006) — thực tế in trên nhãn cả 2 thùng của VD006 đều là 240, khác công thức.
// Tức quantity là thuộc tính RIÊNG của từng thùng, độc lập với công thức đang chạy trên
// thùng đó. Cột này nullable — thùng nào chưa xác nhận số thật thì QR fallback về
// quantity mặc định trong chemical_formula_groups (xem ChemicalFormulaGroup::buildQrText).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_chemical_channels', function (Blueprint $table) {
            $table->integer('quantity')->nullable()->after('chemical_code');
        });
    }

    public function down(): void
    {
        Schema::table('machine_chemical_channels', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
