<?php
// backend/app/Models/DeviceEvent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceEvent extends Model
{
    protected $table = 'device_events';
    
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'event_type',
        'occurred_at',
        'detail',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'detail' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
