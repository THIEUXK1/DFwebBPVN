<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceAssignment extends Model
{
    protected $table = 'device_assignments';

    protected $fillable = [
        'workstation_id',
        'device_id',
        'assignment_type',
        'active',
        'assigned_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'workstation_id');
    }
}
