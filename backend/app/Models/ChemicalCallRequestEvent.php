<?php
// backend/app/Models/ChemicalCallRequestEvent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalCallRequestEvent extends Model
{
    protected $table = 'chemical_call_request_events';

    protected $fillable = [
        'request_id',
        'event_type',
        'occurred_at',
        'actor_user_id',
        'actor_workstation_id',
        'before_status',
        'after_status',
        'note',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(ChemicalCallRequest::class, 'request_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorWorkstation()
    {
        return $this->belongsTo(Workstation::class, 'actor_workstation_id');
    }
}
