<?php
// backend/app/Models/PrintAttempt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintAttempt extends Model
{
    protected $table = 'print_attempts';

    protected $fillable = [
        'print_job_id',
        'attempt_no',
        'status',
        'device_id',
        'started_at',
        'finished_at',
        'error_detail',
    ];

    protected $casts = [
        'attempt_no' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function printJob()
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
