<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cause extends Model
{
    protected $table = 'causes';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'cause_name',
        'category',
        'temperature_rule',
        'steam_supply_pressure_rule',
        'air_pressure_rule',
        'speed_rule',
        'dye_tank_dancer_rule',
        'out_steam_dancer_rule',
        'humidity_rule',
        'steam_dancer_rule',
        'washing_dancer_rule',
        'dryer_dancer_rule',
        'pr01',
        'pr02',
        'pr03',
        'pr04',
        'pr05',
        'pr06',
        'severity',
        'verify_level',
        'repair_priority',
        'is_active'
    ];

    protected $casts = [
        'pr01' => 'boolean',
        'pr02' => 'boolean',
        'pr03' => 'boolean',
        'pr04' => 'boolean',
        'pr05' => 'boolean',
        'pr06' => 'boolean',
        'is_active' => 'boolean'
    ];
}
