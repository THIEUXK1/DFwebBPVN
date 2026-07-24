<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PrintJob extends Model
{
    protected $table = 'print_jobs';

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
        'workstation_id',
        'label_payload',
        'printer_connection_type',
        'printer_address',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'processed_at' => 'datetime',
        'retry_policy' => 'array',
        'expiry' => 'datetime',
    ];

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }

    public function attempts()
    {
        return $this->hasMany(PrintAttempt::class, 'print_job_id')->orderBy('attempt_no');
    }

    public function events()
    {
        return $this->hasMany(PrintJobEvent::class, 'print_job_id')->orderBy('event_time');
    }
}
