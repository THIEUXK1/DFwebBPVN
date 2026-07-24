<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeMaterial extends Model
{
    protected $table = 'recipe_materials';

    public $timestamps = false;

    protected $fillable = [
        'recipe_version_id',
        'material_code',
        'concentration',
        'process_code',
        'tank_number',
        'temperature',
    ];

    protected $casts = [
        'concentration' => 'float',
        'tank_number' => 'integer',
        'temperature' => 'float',
    ];

    public function recipeVersion()
    {
        return $this->belongsTo(RecipeVersion::class, 'recipe_version_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_code', 'code');
    }
}
