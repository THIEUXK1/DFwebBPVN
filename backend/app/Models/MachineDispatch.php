<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MachineDispatch extends Model
{
    protected $table = 'machine_dispatches';

    protected $keyType = 'string';
    public $incrementing = false;
    
    public $timestamps = false;

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
        'legacy_row_no',
        'legacy_id',
        'batch_id',
        'confirm_1',
        'confirm_2',
        'sending_value',
        'sent_value',
        'confirmed_at_1',
        'confirmed_at_2',
        'sent_at',
        'is_sent',
        'scale_checked',
        'raw_qr_dye',
        'raw_qr_chemical',
        'queue_state',
        'source_table',
        'originating_station_code',
        'locked_by',
        'locked_at',
    ];

    protected $casts = [
        'confirmed_at_1' => 'datetime',
        'confirmed_at_2' => 'datetime',
        'sent_at' => 'datetime',
        'is_sent' => 'boolean',
        'scale_checked' => 'boolean',
        'locked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * hasMany thay vì hasOne->latestOfMany(): latestOfMany() dùng MAX(id) cho join
     * aggregate mặc định dù đã chỉ định cột sắp xếp khác — Postgres không có MAX(uuid),
     * lỗi 500 mọi lúc. Controller tự lấy phần tử đầu (đã sắp created_at desc) khi cần
     * đúng 1 bản ghi mới nhất.
     */
    public function printJobs()
    {
        return $this->hasMany(PrintJob::class, 'dispatch_id')->orderByDesc('created_at');
    }

    public function routingDecision()
    {
        return $this->belongsTo(RoutingDecision::class, 'routing_decision_id');
    }

    public function bpdbTaskLink()
    {
        return $this->hasOne(BpdbTaskLink::class, 'dispatch_id');
    }
}
