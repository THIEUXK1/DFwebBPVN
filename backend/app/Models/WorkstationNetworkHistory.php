<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkstationNetworkHistory extends Model
{
    protected $table = 'workstation_network_history';

    protected $fillable = [
        'workstation_id',
        'ip_address',
        'hostname',
        'network_segment',
        'first_seen_at',
        'last_seen_at',
        'status',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'workstation_id');
    }
}
