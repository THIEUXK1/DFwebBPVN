<?php
// backend/database/migrations/2026_07_28_000001_create_chemical_formula_groups_table.php
//
// Phát hiện 2026-07-28 khi đối chiếu QR thật đang dán ở xưởng: QR "Báo phát AC" KHÔNG
// gắn theo từng máy/thùng như bảng chemical_dispatch_labels (2026_07_27) — nó gắn theo
// CÔNG THỨC PHA HÓA CHẤT, và nhiều máy khác nhau (khác cả số thùng) dùng CHUNG 1 QR nếu
// cùng công thức. Định dạng QR thật (xác nhận trực tiếp từ ảnh chụp, không suy diễn):
//   #<code1>-<code2>\r\n<dosing_step>\r\n<quantity>\r\n<unit_weight_1>\r\n<total_weight_1>
//   [\r\n<unit_weight_2>\r\n<total_weight_2>]#
// (nhóm chỉ 1 hóa chất thì code2 rỗng, bỏ hẳn cặp unit/total_weight_2 — vd "#AC68-...#").
// unit_weight lưu THẲNG giá trị thật trên QR, KHÔNG tra qua chemical_weight_references
// nữa — đã phát hiện ít nhất 1 trường hợp (AC123+AC122) giá trị thật trên QR KHÁC với
// kết quả tra bảng "semi" (lệch 40 vs 17), tức QR mới là nguồn sự thật, không phải bảng
// tra. 7 công thức đã xác nhận trực tiếp qua ảnh chụp thật; VIỆC MÁY NÀO DÙNG CÔNG THỨC
// NÀO (mapping máy -> nhóm) CHƯA đưa vào bảng này — dữ liệu mô tả mapping còn mâu thuẫn
// (vd VD12/VD13 xuất hiện ở 3 nhóm khác nhau cùng lúc), cần xác nhận lại từ ảnh gốc
// trước khi model hóa quan hệ máy<->công thức.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FORMULAS = [
        ['code_1' => 'AC68', 'code_2' => null, 'dosing_step' => 4, 'quantity' => 150, 'unit_weight_1' => 38, 'total_weight_1' => 130000, 'unit_weight_2' => null, 'total_weight_2' => null],
        ['code_1' => 'AC77', 'code_2' => null, 'dosing_step' => 2, 'quantity' => 150, 'unit_weight_1' => 37, 'total_weight_1' => 50000, 'unit_weight_2' => null, 'total_weight_2' => null],
        ['code_1' => 'AC77', 'code_2' => 'AC78', 'dosing_step' => 6, 'quantity' => 150, 'unit_weight_1' => 37, 'total_weight_1' => 100000, 'unit_weight_2' => 40, 'total_weight_2' => 50000],
        ['code_1' => 'VN62', 'code_2' => '0554', 'dosing_step' => 5, 'quantity' => 150, 'unit_weight_1' => 34, 'total_weight_1' => 2000, 'unit_weight_2' => 25, 'total_weight_2' => 1250],
        // "NEW" là nhãn CHỮ THẬT in trên QR (không phải mã hóa chất chuẩn AC122) — giữ
        // nguyên văn, không đoán/thay bằng mã khác.
        ['code_1' => 'AC123', 'code_2' => 'NEW', 'dosing_step' => 16, 'quantity' => 150, 'unit_weight_1' => 18, 'total_weight_1' => 75000, 'unit_weight_2' => 17, 'total_weight_2' => 50000],
        ['code_1' => 'AC123', 'code_2' => 'AC122', 'dosing_step' => 6, 'quantity' => 150, 'unit_weight_1' => 18, 'total_weight_1' => 100000, 'unit_weight_2' => 40, 'total_weight_2' => 50000],
        ['code_1' => 'AC20', 'code_2' => '0553', 'dosing_step' => 5, 'quantity' => 150, 'unit_weight_1' => 30, 'total_weight_1' => 12500, 'unit_weight_2' => 8, 'total_weight_2' => 250],
    ];

    public function up(): void
    {
        Schema::create('chemical_formula_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code_1', 100);
            $table->string('code_2', 100)->nullable();
            $table->integer('dosing_step')->nullable();
            $table->integer('quantity')->nullable();
            $table->decimal('unit_weight_1', 12, 3)->nullable();
            $table->decimal('total_weight_1', 12, 3)->nullable();
            $table->decimal('unit_weight_2', 12, 3)->nullable();
            $table->decimal('total_weight_2', 12, 3)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique(['code_1', 'code_2']);
        });

        $now = now();
        DB::table('chemical_formula_groups')->insert(array_map(
            fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now],
            self::FORMULAS
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_formula_groups');
    }
};
