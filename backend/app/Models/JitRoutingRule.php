<?php
// backend/app/Models/JitRoutingRule.php
// Ported from DFwed2 (Postgres + `app.` schema) -> adapted for DFwed (SQLite, no schema
// prefix). UUID primary key generated in PHP (see BpdbTaskLink for the same note).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class JitRoutingRule extends Model
{
    protected $table = 'jit_routing_rules';

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });
    }

    protected $fillable = [
        'machine_code',
        'tank_code',
        'water_level',
        'jit_queue_code',
        'b24_route',
        'qr_mode',
        'routing_system_id',
        'priority',
        'is_active',
        'effective_from',
        'effective_to',
        'source_type',
        'source_reference',
        'overridden_by_user_id',
        'override_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'water_level' => 'integer',
        'priority' => 'integer',
    ];
}
