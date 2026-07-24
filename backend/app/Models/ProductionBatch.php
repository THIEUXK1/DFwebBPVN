<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductionBatch extends Model
{
    protected $table = 'production_batches';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    // Disable Laravel default timestamps since we only have created_at in the legacy design
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
        'legacy_batch_id',
        'color',
        'product_code',
        'machine_id',
        'tank_id',
        'level_code',
        'status',
        'cloth_weight',
        'raw_qr_dye',
        'raw_qr_chemical',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cloth_weight' => 'float',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function dispatches()
    {
        return $this->hasMany(MachineDispatch::class, 'batch_id');
    }
}
