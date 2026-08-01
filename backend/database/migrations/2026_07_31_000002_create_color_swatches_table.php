<?php
// backend/database/migrations/2026_07_31_000002_create_color_swatches_table.php
//
// Bảng tra "mã màu -> màu hiển thị thật" đồng bộ từ VN-MES (module Sedo Planboard).
//
// Vì sao cần bảng riêng thay vì gọi MES lúc render: BPDB (nguồn của Gantt hiện tại,
// dbo.SUP_Tasks) KHÔNG có bất kỳ cột màu nào — đã dò toàn bộ 6 database đọc được trên
// SQL Server nhà máy ngày 2026-07-31 (BPDB/BPVN2025/INTEGDB/StandardDB/INTERFACE_SCC,
// riêng ChaiZong1 bị từ chối quyền): 0 cột %rgb%/%color% mang màu thành phẩm. Màu thật
// chỉ có trong MES, lấy qua POST /mes/rsedo/tBatch/getDataForGantt (~5.5MB/lần, phải
// đăng nhập) — quá nặng để gọi mỗi lần mở Gantt, nên cache lại thành bảng tra.
//
// Nguồn màu: trường cRgbcolor của MES, mã hóa BGR đóng gói (chuẩn Windows COLORREF):
//   R = n & 0xFF;  G = (n >> 8) & 0xFF;  B = (n >> 16) & 0xFF
// KHÔNG phải RGB thường — công thức lấy đúng từ getRGBByNum() trong ganttDrawerSVG.js
// của chính MES, đã đối chiếu thực tế: tiền tố mã màu khớp họ màu giải mã ra (A+ -> đỏ
// booc-đô #7A3642, AP -> hồng phấn #E7B4CC, B+ -> xanh navy #16447D).
//
// Giá trị 15785437 (#DDDDF0) là màu MẶC ĐỊNH "chưa gán màu" của MES (1424/3546 bản ghi
// lúc khảo sát) — lệnh đồng bộ loại bỏ giá trị này, không lưu vào bảng.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_swatches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('color_code', 100);
            // #RRGGBB đã quy đổi sẵn — frontend dùng thẳng, không phải tự giải mã BGR.
            $table->string('rgb_hex', 7);
            // Giữ nguyên số gốc của MES để đối soát ngược khi nghi ngờ sai màu.
            $table->bigInteger('source_value')->nullable();
            $table->string('source', 30)->default('MES_SEDO');
            // Mã hàng gần nhất thấy đi kèm mã màu này — chỉ để tham khảo/đối soát,
            // KHÔNG dùng làm khóa tra (1 mã màu có thể chạy trên nhiều mã hàng).
            $table->string('sample_article_code', 100)->nullable();
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();

            $table->unique('color_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_swatches');
    }
};
