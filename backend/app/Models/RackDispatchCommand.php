<?php
// backend/app/Models/RackDispatchCommand.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Một lệnh gửi mã rack sang hệ pha màu, chờ Local Agent lấy về và thực thi.
 * Port phần "SEND OVER 6" (Mod_sendRackauto) của workbook cân lớn.
 */
class RackDispatchCommand extends Model
{
    protected $table = 'rack_dispatch_commands';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_DONE = 'DONE';
    public const STATUS_FAILED = 'FAILED';

    /** Số ô rack ứng dụng đích nhận trong MỘT lượt — đúng 6 toạ độ của FireRackBatch. */
    public const RACK_SLOTS = 6;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'workstation_code',
        'action',
        'racks',
        'idempotency_key',
        'status',
        'error_message',
        'picked_up_at',
        'completed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'racks' => 'array',
        'created_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
