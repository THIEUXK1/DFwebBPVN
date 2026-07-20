<?php
// backend/database/migrations/2026_07_17_000001_create_wave1_foundation_tables.php

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
        // 1. app.workstation_types
        Schema::create('workstation_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('display_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Add workstation_type_id to app.workstations
        Schema::table('workstations', function (Blueprint $table) {
            $table->unsignedSmallInteger('workstation_type_id')->nullable();
            $table->foreign('workstation_type_id')->references('id')->on('workstation_types')->onDelete('set null');
        });

        // 3. app.devices
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->bigInteger('workstation_id')->nullable();
            $table->string('device_type', 20); // 'PRINTER'|'SCALE'|'SCANNER'|'DISPLAY'|'LOCAL_AGENT'
            $table->string('driver_protocol', 50)->nullable();
            $table->string('status', 20)->default('PENDING_REGISTRATION');
            $table->timestampTz('last_heartbeat_at')->nullable();
            $table->json('configuration')->default('{}');
            $table->string('agent_version', 30)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('set null');
            $table->index('workstation_id');
        });

        // 4. app.device_heartbeats
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('device_id');
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('agent_timestamp')->nullable();
            $table->string('status', 20)->nullable();
            $table->json('payload')->nullable();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index('received_at');
        });

        // 5. app.device_events
        Schema::create('device_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('device_id');
            $table->string('event_type', 30);
            $table->timestampTz('occurred_at')->useCurrent();
            $table->json('detail')->nullable();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
        });

        // 6. app.feature_flags
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('device_heartbeats');
        Schema::dropIfExists('devices');

        Schema::table('workstations', function (Blueprint $table) {
            $table->dropForeign(['workstation_type_id']);
            $table->dropColumn('workstation_type_id');
        });

        Schema::dropIfExists('workstation_types');
    }
};
