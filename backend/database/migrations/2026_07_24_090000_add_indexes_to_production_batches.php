<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProductionBatchController@index sắp xếp ORDER BY created_at DESC và lọc theo
 * status/machine_id ở mọi lần tải /production-batches và /production-batches/list.
 * PostgreSQL không tự tạo index cho cột khóa ngoại (khác MySQL) nên machine_id/tank_id
 * cũng chưa có index — thêm ở đây để trang tải nhanh khi bảng lớn dần.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('status');
            $table->index('machine_id');
            $table->index('tank_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status']);
            $table->dropIndex(['machine_id']);
            $table->dropIndex(['tank_id']);
        });
    }
};
