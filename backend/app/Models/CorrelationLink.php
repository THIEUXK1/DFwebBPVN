<?php
// backend/app/Models/CorrelationLink.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrelationLink extends Model
{
    protected $table = 'correlation_links';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dispatch_id',
        'weighing_job_id',
        'match_method',
        'confidence',
        'matched_on',
        'status',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'matched_on' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }

    public function weighingJob()
    {
        return $this->belongsTo(WeighingJob::class, 'weighing_job_id');
    }
}
