<?php
// backend/app/Models/DeviceHeartbeat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceHeartbeat extends Model
{
    protected $table = 'device_heartbeats';
    
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'received_at',
        'agent_timestamp',
        'status',
        'payload',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'agent_timestamp' => 'datetime',
        'payload' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
