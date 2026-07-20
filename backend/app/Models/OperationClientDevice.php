<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationClientDevice extends Model
{
    protected $table = 'operation_client_devices';

    protected $fillable = [
        'operation_client_id',
        'device_id',
        'device_role',
        'is_default',
        'priority',
        'enabled',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }

    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
