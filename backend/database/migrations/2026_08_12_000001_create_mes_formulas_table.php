<?php
// backend/database/migrations/2026_08_12_000001_create_mes_formulas_table.php
//
// Bảng cache "công thức nhuộm (thuốc nhuộm + hóa chất phụ trợ)" đồng bộ từ VN-MES module
// 织带-配方 (mesPfM). Song sinh với mes_batch_completions và color_swatches: cùng lý do —
// backend production (CS-SERVER) KHÔNG với tới MES nội bộ, nên không gọi MES lúc render mà
// đồng bộ định kỳ thành bảng tra nội bộ (job trên máy .52 với tới MES).
//
// Khóa tra = pfm_pfno (số công thức, vd "VPF2606120001"). Mỗi mẻ VD trong
// mes_batch_completions.raw mang sẵn pfmPfno -> panel "bấm vào mẻ" join sang bảng này để
// hiện danh sách liệu MES cạnh dữ liệu cân thật của BPDB (SUP_Storico).
//
// Nguồn: rbase/mesPfM/list (lấy head id theo pfmPfno) -> rbase/mesPfM/infoByHeadId
// (pfMPfList = các dòng liệu). defRltypeId phân loại: 'AC*' = hóa chất phụ trợ (助剂),
// còn lại (A/B/...) = thuốc nhuộm (染料).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mes_formulas', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Khóa tự nhiên = số công thức MES (pfmPfno). Duy nhất — upsert theo cột này.
            $table->string('pfm_pfno', 64)->unique('mes_formulas_pfno_uq');

            // Head id nội bộ của MES (dùng lại khi cần gọi infoByHeadId lần sau).
            $table->string('formula_id', 64)->nullable();

            $table->string('color_code', 100)->nullable();
            $table->string('article_code', 100)->nullable();
            $table->string('machine_code', 32)->nullable();

            $table->unsignedSmallInteger('dye_count')->default(0);
            $table->unsignedSmallInteger('aux_count')->default(0);

            // Danh sách liệu đã chuẩn hóa: [{code,name,amount,amount3,unit,order,typeId,kind}]
            // kind = 'dye' | 'aux'. Giữ luôn raw pfMPfList gốc để đối soát/mở rộng sau.
            $table->jsonb('materials')->nullable();
            $table->jsonb('raw')->nullable();

            $table->string('source', 30)->default('MES_MESPFM');
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();

            $table->index('synced_at', 'mes_formulas_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mes_formulas');
    }
};
