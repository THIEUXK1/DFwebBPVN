<?php
// backend/app/Models/WeighingSample.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeighingSample extends Model
{
    protected $table = 'weighing_samples';

    protected $fillable = [
        'job_item_id',
        'device_id',
        'sequence_no',
        'device_timestamp',
        'agent_timestamp',
        'server_received_at',
        'raw_value',
        'cleaned_value',
        'unit',
        'is_stable',
        'quality_code',
        'scale_algorithm_version',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'device_timestamp' => 'datetime',
        'agent_timestamp' => 'datetime',
        'server_received_at' => 'datetime',
        'cleaned_value' => 'decimal:6',
        'is_stable' => 'boolean',
    ];

    public function jobItem()
    {
        return $this->belongsTo(WeighingJobItem::class, 'job_item_id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
