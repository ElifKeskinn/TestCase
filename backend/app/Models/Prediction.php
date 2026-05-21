<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prediction — per team × week snapshot (§4.2).
 *
 * Application invariant (NOT DB CHECK): sum(probability per week) == 100,
 * enforced by largest remainder method in ChampionshipPredictor.
 */
class Prediction extends Model
{
    public $timestamps = false;

    protected $fillable = ['team_id', 'week', 'champion_probability', 'computed_at'];

    protected $casts = [
        'team_id' => 'integer',
        'week' => 'integer',
        'champion_probability' => 'float',
        'computed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
