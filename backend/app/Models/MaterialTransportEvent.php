<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransportEvent extends Model
{
    protected $table = 'material_transport_events';

    protected $keyType = 'string';
    public $incrementing = false;
    
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'transport_id',
        'status',
        'operator_id',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function transport()
    {
        return $this->belongsTo(MaterialTransport::class, 'transport_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
