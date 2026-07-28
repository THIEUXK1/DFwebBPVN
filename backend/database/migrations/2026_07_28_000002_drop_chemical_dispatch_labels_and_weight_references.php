<?php
// backend/database/migrations/2026_07_28_000002_drop_chemical_dispatch_labels_and_weight_references.php
//
// Gỡ hệ thống nhập tay theo THÙNG (2026_07_27) — đã bị thay thế bởi
// chemical_formula_groups (2026_07_28_000001): thùng tự tra ra công thức đang active
// qua machine_chemical_channels.chemical_code (xem
// ChemicalFormulaGroup::lookupByCombinedCode), không cần cấu hình tay theo từng thùng
// nữa. Dữ liệu 2 dòng cũ trong chemical_dispatch_labels chỉ là số liệu đặt tạm/không
// chính xác (VD006 mượn tạm số của VD017, VD008 trùng đúng công thức VN62+0554 nhưng
// sai quantity 240 vs 150 thật) — không cần bảo toàn.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('chemical_dispatch_labels');
        Schema::dropIfExists('chemical_weight_references');
    }

    public function down(): void
    {
        // Không tái tạo — bảng cũ đã bị thay thế hoàn toàn bởi chemical_formula_groups,
        // rollback migration này không có ý nghĩa khôi phục dữ liệu nghiệp vụ.
    }
};
