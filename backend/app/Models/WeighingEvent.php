<?php
// backend/app/Models/WeighingEvent.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeighingEvent extends Model
{
    protected $table = 'weighing_events';

    protected $fillable = [
        'job_item_id',
        'event_type',
        'occurred_at',
        'actor_user_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function jobItem()
    {
        return $this->belongsTo(WeighingJobItem::class, 'job_item_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
