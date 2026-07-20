<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TroubleshootingCase extends Model
{
    protected $table = 'troubleshooting_cases';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'batch_id',
        'stage_id',
        'status',
        'reporter_id',
        'created_at',
        'resolved_at',
        'actual_cause_id',
        'resolution_notes',
        'effectiveness_rating'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'resolved_at' => 'datetime',
        'effectiveness_rating' => 'integer'
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'stage_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function actualCause(): BelongsTo
    {
        return $this->belongsTo(Cause::class, 'actual_cause_id');
    }

    public function problems(): BelongsToMany
    {
        return $this->belongsToMany(Problem::class, 'case_problems', 'case_id', 'problem_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(CaseEvidence::class, 'case_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(CaseRecommendation::class, 'case_id');
    }
}
