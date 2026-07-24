<?php
// backend/app/Models/ChemicalCallRequest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalCallRequest extends Model
{
    protected $table = 'chemical_call_requests';
    
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
        'channel_id',
        'machine_id',
        'status',
        'requested_at',
        'requested_by_user_id',
        'requested_by_workstation_id',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'confirmed_at',
        'confirmed_by_user_id',
        'confirmed_by_workstation_id',
        'cancelled_at',
        'cancelled_reason',
        'idempotency_key',
        'row_version',
        'legacy_source',
        'legacy_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function channel()
    {
        return $this->belongsTo(MachineChemicalChannel::class, 'channel_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requestedAtWorkstation()
    {
        return $this->belongsTo(Workstation::class, 'requested_by_workstation_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    public function confirmedAtWorkstation()
    {
        return $this->belongsTo(Workstation::class, 'confirmed_by_workstation_id');
    }

    public function events()
    {
        return $this->hasMany(ChemicalCallRequestEvent::class, 'request_id');
    }
}
