<?php
// backend/database/migrations/2026_07_27_000002_create_chemical_dispatch_labels_table.php
//
// Cấu hình "Báo phát AC" cố định theo từng máy — tương đương sheet "QR" +
// Mod_MAKE_QR.TaoQR_chemical trong "6.báo phát AC- 151.xlsm". Xác nhận nghiệp vụ
// (2026-07-27): 2 mã hóa chất phụ trợ + khối lượng của 1 máy là dữ liệu CỐ ĐỊNH, không
// tính lại theo từng lô/đơn hàng — nên đây là bảng cấu hình 1-1 theo machine_id, không
// gắn vào production_batches/weighing_jobs.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemical_dispatch_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('machine_id');
            $table->integer('dosing_step_1')->nullable();
            $table->integer('dosing_step_2')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('chemical_code_1', 100);
            $table->decimal('total_weight_1', 12, 3);
            $table->string('chemical_code_2', 100);
            $table->decimal('total_weight_2', 12, 3);
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('cascade');
            $table->unique('machine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_dispatch_labels');
    }
};
