<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Recipe extends Model
{
    protected $table = 'recipes';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'color_code',
        'product_code',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function versions()
    {
        return $this->hasMany(RecipeVersion::class, 'recipe_id')->orderBy('version', 'desc');
    }
    
    public function latestVersion()
    {
        return $this->hasOne(RecipeVersion::class, 'recipe_id')->orderBy('version', 'desc');
    }
}
