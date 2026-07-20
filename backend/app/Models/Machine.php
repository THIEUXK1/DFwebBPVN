<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $table = 'machines';
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tanks()
    {
        return $this->hasMany(Tank::class, 'machine_id');
    }

    public function chemicalChannels()
    {
        return $this->hasMany(MachineChemicalChannel::class, 'machine_id');
    }
}
