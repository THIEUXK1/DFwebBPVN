<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseRecommendation extends Model
{
    protected $table = 'case_recommendations';
    public $timestamps = false;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'case_id',
        'cause_id',
        'score',
        'rank',
        'recommendation_text'
    ];

    protected $casts = [
        'score' => 'double',
        'rank' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function cause(): BelongsTo
    {
        return $this->belongsTo(Cause::class, 'cause_id');
    }
}
