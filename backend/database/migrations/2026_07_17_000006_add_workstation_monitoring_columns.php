<?php
// backend/database/migrations/2026_07_17_000006_add_workstation_monitoring_columns.php

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
        Schema::table('workstations', function (Blueprint $table) {
            if (!Schema::hasColumn('workstations', 'agent_version')) {
                $table->string('agent_version', 50)->nullable();
            }
            if (!Schema::hasColumn('workstations', 'last_heartbeat')) {
                $table->timestampTz('last_heartbeat')->nullable();
            }
            if (!Schema::hasColumn('workstations', 'suspended')) {
                $table->boolean('suspended')->default(false);
            }
            if (!Schema::hasColumn('workstations', 'active_errors')) {
                $table->json('active_errors')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn(['agent_version', 'last_heartbeat', 'suspended', 'active_errors']);
        });
    }
};
