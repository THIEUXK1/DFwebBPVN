<?php
// backend/app/Models/Device.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'devices';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'code',
        'operation_client_id',
        'device_type',
        'driver_protocol',
        'status',
        'last_heartbeat_at',
        'configuration',
        'agent_version',
        'is_enabled',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'configuration' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function workstation()
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }

    public function heartbeats()
    {
        return $this->hasMany(DeviceHeartbeat::class, 'device_id');
    }

    public function events()
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }
}
