<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WeighingJob extends Model
{
    protected $table = 'weighing_jobs';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'production_batch_id',
        'job_type',
        'workstation_type',
        'sequence_no',
        'status',
        'assigned_operation_client_id',
        'idempotency_key',
        'assigned_workstation_id',
        'received_at',
        'started_at',
        'completed_at',
        'locked_by_user_id',
        'locked_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'locked_at' => 'datetime',
        'sequence_no' => 'integer',
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

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function items()
    {
        return $this->hasMany(WeighingJobItem::class, 'weighing_job_id')->orderBy('sequence_no', 'asc');
    }

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'assigned_operation_client_id');
    }

    public function getAssignedWorkstationIdAttribute()
    {
        return $this->assigned_operation_client_id;
    }

    public function setAssignedWorkstationIdAttribute($value)
    {
        $this->attributes['assigned_operation_client_id'] = $value;
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }
}
