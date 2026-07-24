<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. app.workstations
        Schema::create('workstations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('type', 50); // ORDER_DESK, PRODUCTION_DISPATCH, DYE_WEIGHING, etc.
            $table->string('location', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->string('assigned_scale_device_id', 100)->nullable();
            $table->string('assigned_printer_device_id', 100)->nullable();
            $table->json('configuration')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();
        });

        // 2. app.weighing_jobs
        Schema::create('weighing_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_batch_id');
            $table->string('job_type', 30); // DYE, CHEMICAL, A11, DLG, AUXILIARY
            $table->string('workstation_type', 50); // DYE_WEIGHING, CHEMICAL_WEIGHING, etc.
            $table->integer('sequence_no')->default(1);
            $table->string('status', 30)->default('PENDING'); // PENDING, RECEIVED, IN_PROGRESS, COMPLETED, etc.
            $table->bigInteger('assigned_workstation_id')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->uuid('locked_by_user_id')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->timestamps();

            $table->foreign('production_batch_id')->references('id')->on('production_batches')->onDelete('cascade');
            $table->foreign('assigned_workstation_id')->references('id')->on('workstations')->onDelete('set null');
            $table->foreign('locked_by_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 3. app.weighing_job_items
        Schema::create('weighing_job_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('weighing_job_id');
            $table->string('material_code', 100);
            $table->decimal('planned_weight', 18, 6);
            $table->decimal('tolerance_minus', 18, 6);
            $table->decimal('tolerance_plus', 18, 6);
            $table->integer('sequence_no')->default(1);
            $table->decimal('actual_weight', 18, 6)->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING, IN_PROGRESS, COMPLETED, etc.
            $table->uuid('label_id')->nullable();
            $table->timestampTz('completed_at')->nullable();

            $table->foreign('weighing_job_id')->references('id')->on('weighing_jobs')->onDelete('cascade');
            $table->foreign('material_code')->references('code')->on('materials')->onDelete('restrict');
        });

        // 4. app.material_labels
        Schema::create('material_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_batch_id');
            $table->uuid('weighing_job_id');
            $table->string('material_type', 30);
            $table->string('material_code', 100)->nullable();
            $table->decimal('weight', 18, 6);
            $table->integer('reprint_count')->default(0);
            $table->text('reprint_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('production_batch_id')->references('id')->on('production_batches')->onDelete('cascade');
            $table->foreign('weighing_job_id')->references('id')->on('weighing_jobs')->onDelete('cascade');
            $table->foreign('material_code')->references('code')->on('materials')->onDelete('set null');
        });

        // 5. Add keys/columns to weighing_job_items in label_id
        Schema::table('weighing_job_items', function (Blueprint $table) {
            $table->foreign('label_id')->references('id')->on('material_labels')->onDelete('set null');
        });

        // 6. Alter app.scale_measurements
        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->uuid('weighing_job_item_id')->nullable();
            $table->foreign('weighing_job_item_id')->references('id')->on('weighing_job_items')->onDelete('set null');
        });

        // 7. Alter app.material_transports
        Schema::table('material_transports', function (Blueprint $table) {
            $table->uuid('weighing_job_id')->nullable();
            $table->uuid('material_label_id')->nullable();
            
            $table->foreign('weighing_job_id')->references('id')->on('weighing_jobs')->onDelete('set null');
            $table->foreign('material_label_id')->references('id')->on('material_labels')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_transports', function (Blueprint $table) {
            $table->dropColumn(['weighing_job_id', 'material_label_id']);
        });

        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->dropColumn(['weighing_job_item_id']);
        });

        Schema::dropIfExists('weighing_job_items');
        Schema::dropIfExists('material_labels');
        Schema::dropIfExists('weighing_jobs');
        Schema::dropIfExists('workstations');
    }
};
