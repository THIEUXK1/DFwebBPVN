<?php
// backend/app/Models/MesBatchCompletion.php
//
// Cache giờ kết thúc nhuộm thật của mẻ, đồng bộ từ MES eBatchLine (status=60).
// Xem migration create_mes_batch_completions_table + SyncMesBatchCompletionsCommand.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesBatchCompletion extends Model
{
    protected $table = 'mes_batch_completions';

    protected $fillable = [
        'mes_id',
        'batch_no',
        'line_no',
        'machine_code',
        'machine_no_raw',
        'color_code',
        'article_code',
        'order_ucode',
        'begin_time',
        'end_time',
        'end_by_name',
        'shift',
        'manu_step',
        'status',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'begin_time' => 'datetime',
        'end_time' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
