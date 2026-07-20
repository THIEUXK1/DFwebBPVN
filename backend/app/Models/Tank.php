<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tank extends Model
{
    protected $table = 'tanks';
    public $timestamps = false;

    protected $fillable = [
        'machine_id',
        'code',
        'name',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
