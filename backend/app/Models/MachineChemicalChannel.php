<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineChemicalChannel extends Model
{
    protected $table = 'machine_chemical_channels';
    
    public $timestamps = false;

    protected $fillable = [
        'machine_id',
        'channel_number',
        'chemical_code',
        'is_active',
        'legacy_id',
    ];

    protected $casts = [
        'channel_number' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
