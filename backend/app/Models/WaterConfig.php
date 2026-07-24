<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterConfig extends Model
{
    protected $table = 'water_configs';

    protected $fillable = [
        'machine_line',
        'process_code',
        'ratio_coefficient',
        'liquor_ratio',
    ];

    protected $casts = [
        'ratio_coefficient' => 'float',
        'liquor_ratio' => 'float',
    ];
}
