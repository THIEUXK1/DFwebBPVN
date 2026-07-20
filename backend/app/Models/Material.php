<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $table = 'materials';

    protected $fillable = [
        'code',
        'name',
        'type',
        'supplier',
        'stock_qty',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stock_qty' => 'float',
    ];
}
