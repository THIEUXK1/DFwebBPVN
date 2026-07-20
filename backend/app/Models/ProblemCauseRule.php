<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProblemCauseRule extends Model
{
    protected $table = 'problem_cause_rules';
    public $timestamps = false;

    protected $fillable = [
        'problem_id',
        'cause_id',
        'cause_score'
    ];

    public function problem()
    {
        return $this->belongsTo(Problem::class, 'problem_id');
    }

    public function cause()
    {
        return $this->belongsTo(Cause::class, 'cause_id');
    }
}
