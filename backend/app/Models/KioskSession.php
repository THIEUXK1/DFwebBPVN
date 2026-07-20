<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskSession extends Model
{
    protected $table = 'kiosk_sessions';

    protected $fillable = [
        'operation_client_id',
        'token',
        'started_at',
        'last_activity_at',
        'expires_at',
        'status',
        'remote_ip',
        'browser_fingerprint',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }
}
