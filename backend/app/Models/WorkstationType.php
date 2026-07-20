<?php
// backend/app/Models/WorkstationType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkstationType extends Model
{
    protected $table = 'workstation_types';

    protected $fillable = [
        'code',
        'display_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workstations()
    {
        return $this->hasMany(Workstation::class, 'workstation_type_id');
    }
}
