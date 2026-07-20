<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkstationAllowedAction extends Model
{
    protected $table = 'workstation_allowed_actions';

    protected $fillable = [
        'workstation_id',
        'action_code',
    ];

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'workstation_id');
    }
}
