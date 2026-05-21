<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Match Eloquent model — table "matches".
 *
 * Class is named MatchModel to avoid the PHP 8 reserved word "match".
 *
 * @property int     $id
 * @property int     $week
 * @property int     $home_team_id
 * @property int     $away_team_id
 * @property ?int    $home_score
 * @property ?int    $away_score
 * @property ?\Carbon\Carbon $played_at
 * @property int     $version          Optimistic lock (§4.5.4).
 * @property int     $editions_count   Audit counter.
 */
class MatchModel extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'week',
        'home_team_id',
        'away_team_id',
        'home_score',
        'away_score',
        'played_at',
        'version',
        'editions_count',
    ];

    protected $casts = [
        'week' => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'played_at' => 'datetime',
        'version' => 'integer',
        'editions_count' => 'integer',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function isPlayed(): bool
    {
        return $this->played_at !== null
            && $this->home_score !== null
            && $this->away_score !== null;
    }
}
