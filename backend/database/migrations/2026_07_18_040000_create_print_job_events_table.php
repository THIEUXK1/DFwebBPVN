<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nhật ký bất biến (append-only) toàn bộ vòng đời 1 PrintJob — theo đúng yêu cầu
// 2026-07-18: JOB_CREATED..CANCELLED, để phân biệt rõ "B. Lịch sử print job" (trạng
// thái tại từng thời điểm) khỏi "C. Lịch sử in thực tế" (print_attempts, đã có sẵn từ
// trước). Không có updated_at/soft-delete — đúng bản chất audit log, không sửa/xóa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_job_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('print_job_id');
            $table->uuid('dispatch_id')->nullable();
            $table->uuid('production_job_id')->nullable(); // = production_batches.id
            $table->string('station_id', 50)->nullable();
            $table->uuid('agent_id')->nullable(); // app.devices.id nếu xác định được thiết bị/agent
            $table->string('printer_name', 150)->nullable();
            $table->string('event_type', 30);
            $table->timestampTz('event_time');
            $table->text('error_message')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('print_job_id')->references('id')->on('print_jobs')->onDelete('cascade');
            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('set null');
            $table->index(['print_job_id', 'event_time']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_job_events');
    }
};
