<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationClientCapability extends Model
{
    protected $table = 'operation_client_capabilities';

    protected $fillable = [
        'operation_client_id',
        'capability_id',
        'enabled',
        'configuration_json',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'configuration_json' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }

    public function capability()
    {
        return $this->belongsTo(Capability::class, 'capability_id');
    }
}
