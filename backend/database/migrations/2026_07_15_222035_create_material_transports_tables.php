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
        Schema::create('material_transports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id');
            $table->string('workstation_id', 50);
            $table->string('status', 30)->default('READY_FOR_TRANSFER');
            $table->integer('sla_minutes')->default(15); // SLA limit in minutes
            $table->timestamp('started_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->text('delay_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('batch_id')->references('id')->on('production_batches')->onDelete('cascade');
        });

        Schema::create('material_transport_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transport_id');
            $table->string('status', 30);
            $table->uuid('operator_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('transport_id')->references('id')->on('material_transports')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_transport_events');
        Schema::dropIfExists('material_transports');
    }
};
