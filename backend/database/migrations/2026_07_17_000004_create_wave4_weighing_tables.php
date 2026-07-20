<?php
// backend/database/migrations/2026_07_17_000004_create_wave4_weighing_tables.php

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
        // 1. app.weighing_samples
        Schema::create('weighing_samples', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('job_item_id');
            $table->uuid('device_id');
            $table->bigInteger('sequence_no');
            $table->timestampTz('device_timestamp')->nullable();
            $table->timestampTz('agent_timestamp')->nullable();
            $table->timestampTz('server_received_at')->useCurrent();
            $table->text('raw_value')->nullable();
            $table->decimal('cleaned_value', 18, 6)->nullable();
            $table->string('unit', 10)->nullable();
            $table->boolean('is_stable')->default(false);
            $table->string('quality_code', 20)->default('OK');
            $table->string('scale_algorithm_version', 10)->nullable();
            $table->timestamps();

            $table->foreign('job_item_id')->references('id')->on('weighing_job_items')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->unique(['device_id', 'sequence_no']);
        });

        // 2. app.weighing_results
        Schema::create('weighing_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_item_id');
            $table->bigInteger('stable_reading_id')->nullable();
            $table->decimal('final_value', 18, 6);
            $table->string('tolerance_status', 10); // 'ACCEPTED'|'WARNING'|'REJECTED'
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->uuid('override_by_user_id')->nullable();
            $table->timestampTz('posted_at')->useCurrent();
            $table->string('policy_version', 10)->nullable();
            $table->timestamps();

            $table->foreign('job_item_id')->references('id')->on('weighing_job_items')->onDelete('cascade');
            $table->foreign('stable_reading_id')->references('id')->on('weighing_samples')->onDelete('set null');
            $table->foreign('override_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('job_item_id');
        });

        // 3. app.weighing_events
        Schema::create('weighing_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('job_item_id');
            $table->string('event_type', 30);
            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('actor_user_id')->nullable();
            $table->timestamps();

            $table->foreign('job_item_id')->references('id')->on('weighing_job_items')->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weighing_events');
        Schema::dropIfExists('weighing_results');
        Schema::dropIfExists('weighing_samples');
    }
};
