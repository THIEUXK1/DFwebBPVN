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
        Schema::table('weighing_job_items', function (Blueprint $table) {
            $table->boolean('override_approved')->default(false);
            $table->text('override_reason')->nullable();
            $table->uuid('override_by')->nullable();

            $table->foreign('override_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weighing_job_items', function (Blueprint $table) {
            $table->dropForeign(['override_by']);
            $table->dropColumn(['override_approved', 'override_reason', 'override_by']);
        });
    }
};
