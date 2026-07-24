<?php
// backend/database/migrations/2026_07_17_000005_create_wave5_correlation_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. app.correlation_links
        Schema::create('correlation_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispatch_id');
            $table->uuid('weighing_job_id');
            $table->string('match_method', 30); // 'EXACT'|'DETERMINISTIC_COMPOSITE'|'PROBABILISTIC'|'MANUAL'
            $table->decimal('confidence', 3, 2); // 0.00 to 1.00
            $table->json('matched_on');
            $table->string('status', 20)->default('LINKED'); // 'LINKED'|'EXCEPTION_QUEUE'|'REJECTED'
            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('cascade');
            $table->foreign('weighing_job_id')->references('id')->on('weighing_jobs')->onDelete('cascade');
            $table->index('dispatch_id');
            $table->index('weighing_job_id');
        });

        // 2. app.legacy_exception_queue_items
        Schema::create('legacy_exception_queue_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type', 30); // 'weighing_job'|'machine_dispatch'
            $table->uuid('entity_id');
            $table->text('reason');
            $table->timestampTz('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_exception_queue_items');
        Schema::dropIfExists('correlation_links');
    }
};
