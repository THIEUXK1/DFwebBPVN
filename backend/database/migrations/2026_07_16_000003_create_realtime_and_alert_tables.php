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
        // 1. app.realtime_events (Transactional Outbox)
        Schema::create('realtime_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type', 100)->index();
            $table->string('entity_type', 100);
            $table->string('entity_id', 100)->index();
            $table->json('payload')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->bigInteger('machine_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();

            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('set null');
            $table->foreign('batch_id')->references('id')->on('production_batches')->onDelete('set null');
        });

        // 2. app.alert_rules
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule_code', 50)->unique();
            $table->string('name', 200);
            $table->string('severity', 20)->default('WARNING'); // INFO, WARNING, CRITICAL
            $table->integer('threshold_seconds')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        // 3. app.alerts
        Schema::create('alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rule_code', 50)->index();
            $table->string('severity', 20)->default('WARNING');
            $table->text('message');
            $table->uuid('batch_id')->nullable();
            $table->bigInteger('machine_id')->nullable();
            $table->string('status', 30)->default('OPEN'); // OPEN, ACKNOWLEDGED, RESOLVED
            $table->uuid('assigned_to')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->text('reason')->nullable();
            $table->text('resolution')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();

            $table->foreign('rule_code')->references('rule_code')->on('alert_rules')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('production_batches')->onDelete('set null');
            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('set null');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('realtime_events');
    }
};
