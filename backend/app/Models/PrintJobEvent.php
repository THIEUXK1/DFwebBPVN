<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrintJobEvent extends Model
{
    protected $table = 'print_job_events';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // Đủ 10 loại theo yêu cầu 2026-07-18 — xem PrintJobEventService cho ngữ nghĩa
    // từng loại và điểm log thật trong pipeline.
    public const TYPES = [
        'JOB_CREATED',
        'JOB_VISIBLE_AT_STATION',
        'PRINTER_SELECTED',
        'PRINT_REQUESTED',
        'AGENT_CLAIMED',
        'SENT_TO_PRINTER',
        'PRINT_SUCCEEDED',
        'PRINT_FAILED',
        'REPRINT_REQUESTED',
        'CANCELLED',
    ];

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
        'print_job_id',
        'dispatch_id',
        'production_job_id',
        'station_id',
        'agent_id',
        'printer_name',
        'event_type',
        'event_time',
        'error_message',
        'correlation_id',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function printJob()
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }
}
