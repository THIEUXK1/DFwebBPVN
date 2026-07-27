<?php
// backend/database/migrations/2026_07_27_000001_create_chemical_weight_references_table.php
//
// Tương đương sheet "semi" cột E:F trong "6.báo phát AC- 151.xlsm", nguồn duy nhất mà
// công thức VLOOKUP(B6, semi!E:F, 2) gốc dùng để tra khối lượng/đơn vị theo mã hóa chất
// phụ trợ (cột A:B của cùng sheet là 1 danh sách khác, không liên quan, không migrate).
// Dữ liệu seed lấy nguyên văn từ semi!E1:F34 (2 dòng trùng mã ở E33/E34 đã gộp).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REFERENCES = [
        ['code' => 'AC91', 'unit_weight' => 1, 'legacy_id' => 1],
        ['code' => 'AC94', 'unit_weight' => 2, 'legacy_id' => 2],
        ['code' => 'PC08', 'unit_weight' => 3, 'legacy_id' => 3],
        ['code' => 'AC82', 'unit_weight' => 4, 'legacy_id' => 4],
        ['code' => 'AC101', 'unit_weight' => 5, 'legacy_id' => 5],
        ['code' => '0507', 'unit_weight' => 6, 'legacy_id' => 6],
        ['code' => '0550', 'unit_weight' => 7, 'legacy_id' => 7],
        ['code' => '0553', 'unit_weight' => 8, 'legacy_id' => 8],
        ['code' => '0574', 'unit_weight' => 9, 'legacy_id' => 9],
        ['code' => '0645', 'unit_weight' => 10, 'legacy_id' => 10],
        ['code' => '0627', 'unit_weight' => 11, 'legacy_id' => 11],
        ['code' => 'AC10', 'unit_weight' => 15, 'legacy_id' => 12],
        ['code' => 'SC28', 'unit_weight' => 32, 'legacy_id' => 13],
        ['code' => 'SC19', 'unit_weight' => 12, 'legacy_id' => 14],
        ['code' => 'SC27', 'unit_weight' => 22, 'legacy_id' => 15],
        ['code' => 'VN62', 'unit_weight' => 34, 'legacy_id' => 16],
        ['code' => 'SC02', 'unit_weight' => 35, 'legacy_id' => 17],
        ['code' => 'AC78', 'unit_weight' => 40, 'legacy_id' => 18],
        ['code' => 'AC77', 'unit_weight' => 37, 'legacy_id' => 19],
        ['code' => 'AC68', 'unit_weight' => 38, 'legacy_id' => 20],
        ['code' => 'AC20', 'unit_weight' => 30, 'legacy_id' => 21],
        ['code' => 'AC06', 'unit_weight' => 19, 'legacy_id' => 22],
        ['code' => 'AC03', 'unit_weight' => 41, 'legacy_id' => 23],
        ['code' => 'AC02', 'unit_weight' => 42, 'legacy_id' => 24],
        ['code' => '0557', 'unit_weight' => 43, 'legacy_id' => 25],
        ['code' => '0554', 'unit_weight' => 25, 'legacy_id' => 26],
        ['code' => '0526', 'unit_weight' => 13, 'legacy_id' => 27],
        ['code' => 'AC63', 'unit_weight' => 39, 'legacy_id' => 28],
        ['code' => '0817', 'unit_weight' => 31, 'legacy_id' => 29],
        ['code' => '0732', 'unit_weight' => 24, 'legacy_id' => 30],
        ['code' => 'AC122', 'unit_weight' => 17, 'legacy_id' => 31],
        ['code' => 'AC123', 'unit_weight' => 18, 'legacy_id' => 32],
    ];

    public function up(): void
    {
        Schema::create('chemical_weight_references', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 100)->unique();
            $table->decimal('unit_weight', 12, 3);
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('chemical_weight_references')->insert(array_map(
            fn ($row) => $row + ['created_at' => $now, 'updated_at' => $now],
            self::REFERENCES
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_weight_references');
    }
};
