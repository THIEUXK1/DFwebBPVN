<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $table = 'parameters';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'parameter_name',
        'unit',
        'lsl',
        'usl',
        'lcl',
        'ucl',
        'score',
        'standard',
        'is_active'
    ];

    protected $casts = [
        'lsl' => 'double',
        'usl' => 'double',
        'lcl' => 'double',
        'ucl' => 'double',
        'score' => 'double',
        'standard' => 'double',
        'is_active' => 'boolean'
    ];
}
