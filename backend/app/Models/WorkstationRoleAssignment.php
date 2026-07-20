<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkstationRoleAssignment extends Model
{
    protected $table = 'workstation_role_assignments';

    protected $fillable = [
        'workstation_id',
        'role_id',
    ];

    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'workstation_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
