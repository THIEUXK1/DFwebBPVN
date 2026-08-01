<?php
// backend/app/Models/ColorSwatch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColorSwatch extends Model
{
    protected $table = 'color_swatches';

    protected $fillable = [
        'color_code',
        'rgb_hex',
        'source_value',
        'source',
        'sample_article_code',
        'synced_at',
    ];

    protected $casts = [
        'source_value' => 'integer',
        'synced_at' => 'datetime',
    ];
}
