<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkstationSession extends Model
{
    protected $table = 'workstation_sessions';

    protected $fillable = [
        'workstation_id',
        'user_id',
        'login_at',
        'logout_at',
        'ip_address',
        'session_status',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
    ];

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'workstation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
