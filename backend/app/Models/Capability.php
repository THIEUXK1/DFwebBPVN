<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    protected $table = 'capabilities';

    protected $fillable = [
        'code',
        'name',
        'category',
    ];

    public function clients()
    {
        return $this->belongsToMany(OperationClient::class, 'operation_client_capabilities', 'capability_id', 'operation_client_id')
            ->withPivot('enabled', 'configuration_json')
            ->withTimestamps();
    }
}
