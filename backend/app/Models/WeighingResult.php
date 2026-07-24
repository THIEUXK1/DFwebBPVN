<?php
// backend/app/Models/WeighingResult.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeighingResult extends Model
{
    protected $table = 'weighing_results';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'job_item_id',
        'stable_reading_id',
        'final_value',
        'tolerance_status',
        'is_override',
        'override_reason',
        'override_by_user_id',
        'posted_at',
        'policy_version',
    ];

    protected $casts = [
        'final_value' => 'decimal:6',
        'is_override' => 'boolean',
        'posted_at' => 'datetime',
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

    public function jobItem()
    {
        return $this->belongsTo(WeighingJobItem::class, 'job_item_id');
    }

    public function stableReading()
    {
        return $this->belongsTo(WeighingSample::class, 'stable_reading_id');
    }

    public function overrideByUser()
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }
}
