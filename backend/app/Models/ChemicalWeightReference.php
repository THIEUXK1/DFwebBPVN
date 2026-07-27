<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalWeightReference extends Model
{
    protected $table = 'chemical_weight_references';

    protected $fillable = [
        'code',
        'unit_weight',
        'legacy_id',
    ];

    protected $casts = [
        'unit_weight' => 'float',
    ];
}
