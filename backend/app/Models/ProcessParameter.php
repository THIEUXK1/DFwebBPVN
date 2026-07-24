<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessParameter extends Model
{
    protected $table = 'process_parameters';

    public $timestamps = false;

    protected $fillable = [
        'recipe_version_id',
        'tension_value',
        'water_ratio_override',
    ];

    protected $casts = [
        'tension_value' => 'float',
        'water_ratio_override' => 'float',
    ];

    public function recipeVersion()
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }
}
