<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standing — derived snapshot, write-through (§4.2).
 *
 * DB CHECK invariants:
 *  - played = won + drawn + lost
 *  - goal_diff = goals_for - goals_against
 *  - points = 3*won + drawn
 */
class Standing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'team_id', 'played', 'won', 'drawn', 'lost',
        'goals_for', 'goals_against', 'goal_diff', 'points', 'updated_at',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'played' => 'integer',
        'won' => 'integer',
        'drawn' => 'integer',
        'lost' => 'integer',
        'goals_for' => 'integer',
        'goals_against' => 'integer',
        'goal_diff' => 'integer',
        'points' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
