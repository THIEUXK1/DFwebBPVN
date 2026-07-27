<?php
// backend/database/migrations/2026_07_27_000003_convert_chemical_dispatch_labels_to_channel_based.php
//
// Sửa lại thiết kế 2026_07_27_000002: phát hiện ngay sau khi seed dữ liệu thật rằng mỗi
// THÙNG (machine_chemical_channels) trong bảng "Gọi hóa chất" đang hoạt động đã tự mang
// sẵn 1 cặp "mã1 + mã2" (vd Thùng 5 = "VN62 + 0554", Thùng 6 = "AC77 + AC78") — đúng khớp
// B3 (chemical_code_1+chemical_code_2) của "Báo phát AC" gốc. 1 máy có nhiều thùng với
// các cặp KHÁC NHAU, nên cấu hình khối lượng phải gắn theo channel_id chứ không gộp theo
// machine_id (sẽ mất dữ liệu của thùng còn lại). Bảng vừa tạo ở migration trước chưa có
// dữ liệu thật nào ngoài 1 dòng demo (VD017 — máy này còn không có thùng nào trong bảng
// gọi hóa chất) nên drop & tạo lại an toàn, không mất dữ liệu nghiệp vụ.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chemical_dispatch_labels');

        Schema::create('chemical_dispatch_labels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('channel_id');
            $table->integer('dosing_step_1')->nullable();
            $table->integer('dosing_step_2')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('total_weight_1', 12, 3);
            $table->decimal('total_weight_2', 12, 3);
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('machine_chemical_channels')->onDelete('cascade');
            $table->unique('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_dispatch_labels');
    }
};
