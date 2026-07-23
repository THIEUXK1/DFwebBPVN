<?php
// backend/app/Models/BpdbTaskLink.php
// Ported from DFwed2 (Postgres + `app.` schema) -> adapted for DFwed (SQLite, no schema
// prefix). Table name has no `app.` prefix. UUID primary key is generated in PHP via the
// same boot()/creating() pattern used by MachineDispatch/ProductionBatch, since SQLite has
// no gen_random_uuid() DB default.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BpdbTaskLink extends Model
{
    protected $table = 'bpdb_task_links';

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
        'dispatch_id',
        'chemical_request_id',
        'internal_request_no',
        'bpdb_task_title',
        'bpdb_task_id',
        'bpdb_task_status',
        'bpdb_is_deleted',
        'bpdb_work_start_time',
        'bpdb_finish_time',
        'bpdb_error_message',
        'jit_routing_rule_id',
        'jit_queue_code',
        'routing_system_id',
        'dye_machine_relationship_id',
        'adapter_status',
        'send_attempts',
        'match_method',
        'last_synced_at',
    ];

    protected $casts = [
        'bpdb_is_deleted' => 'boolean',
        'bpdb_work_start_time' => 'datetime',
        'bpdb_finish_time' => 'datetime',
        'last_synced_at' => 'datetime',
        'send_attempts' => 'integer',
    ];

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }

    public function jitRoutingRule()
    {
        return $this->belongsTo(JitRoutingRule::class, 'jit_routing_rule_id');
    }
}
