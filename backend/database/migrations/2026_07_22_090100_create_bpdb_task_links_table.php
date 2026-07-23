<?php
// backend/database/migrations/2026_07_22_090100_create_bpdb_task_links_table.php
//
// Nền tảng để BPDB Adapter (đọc-only giai đoạn đầu, xem colorservice-trace-report mục
// 09) tìm và liên kết SUP_Tasks thật bên Color Service với chemical_call_requests của
// web. QUAN TRỌNG: BPDB không có FK/ID liên kết Task<->Detail<->History — chỉ join
// bằng SUP_Tasks.TaskTitle dạng text (đã xác minh thật). Vì vậy bpdb_task_title là
// khóa đối chiếu chính, bpdb_task_id chỉ điền SAU KHI đã tìm thấy & xác nhận.
//
// PORTED từ DFwed2 (Postgres + schema `app.`) sang DFwed (SQLite, không có schema
// prefix): bỏ `app.` prefix trên bảng và trên 3 FK (machine_dispatches,
// chemical_call_requests, jit_routing_rules — đều là bảng phẳng có sẵn/được tạo bởi
// migration đứng trước ở DFwed), bỏ default gen_random_uuid() (id sinh trong PHP qua
// BpdbTaskLink::boot()/creating()).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpdb_task_links', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Phía web — dùng bảng THẬT đã có (machine_dispatches = "production job",
            // chemical_call_requests = "chemical request"), không tạo production_jobs
            // song song vì đã có machine_dispatches đóng đúng vai trò này.
            $table->uuid('dispatch_id')->nullable();
            $table->uuid('chemical_request_id')->nullable();
            $table->string('internal_request_no', 100)->nullable();

            // Phía BPDB (Color Service) — bpdb_task_title là khóa đối chiếu THẬT (text,
            // theo đúng format VBA qrProcess: "{color}-{code} {yyyymmddhhmm}"); bpdb_task_id
            // (GUID SUP_Tasks.Id) chỉ có giá trị sau khi Adapter tìm & khớp thành công.
            $table->string('bpdb_task_title', 255)->nullable();
            $table->string('bpdb_task_id', 64)->nullable();
            $table->integer('bpdb_task_status')->nullable();
            $table->boolean('bpdb_is_deleted')->nullable();
            $table->timestampTz('bpdb_work_start_time')->nullable();
            $table->timestampTz('bpdb_finish_time')->nullable();
            $table->text('bpdb_error_message')->nullable();

            // Định tuyến JIT áp dụng cho lần gọi này (snapshot tại thời điểm tạo, để đối
            // chiếu sau này dù jit_routing_rules có thể đổi).
            $table->uuid('jit_routing_rule_id')->nullable();
            $table->string('jit_queue_code', 20)->nullable();
            $table->string('routing_system_id', 64)->nullable();
            $table->string('dye_machine_relationship_id', 64)->nullable();

            // Trạng thái riêng của Adapter (khác bpdb_task_status) — theo dõi tiến trình
            // TÌM/KHỚP task, không phải tiến trình dosing thật.
            $table->string('adapter_status', 30)->default('AWAITING_SCAN');
            $table->integer('send_attempts')->default(0);
            $table->text('match_method')->nullable(); // vd "title_exact", "manual_override"
            $table->timestampTz('last_synced_at')->nullable();

            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('set null');
            $table->foreign('chemical_request_id')->references('id')->on('chemical_call_requests')->onDelete('set null');
            $table->foreign('jit_routing_rule_id')->references('id')->on('jit_routing_rules')->onDelete('set null');

            // Mỗi chemical request chỉ được khớp với ĐÚNG 1 task BPDB hợp lệ (đúng yêu
            // cầu gốc); NULL không bị ràng buộc unique nên nhiều request "chưa khớp"
            // (bpdb_task_id NULL) vẫn tồn tại song song bình thường.
            $table->unique('chemical_request_id');
            $table->unique('bpdb_task_id');
        });

        DB::statement("CREATE INDEX idx_bpdb_task_links_title ON bpdb_task_links (bpdb_task_title)");
        DB::statement("CREATE INDEX idx_bpdb_task_links_adapter_status ON bpdb_task_links (adapter_status)");
    }

    public function down(): void
    {
        Schema::dropIfExists('bpdb_task_links');
    }
};
