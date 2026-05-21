<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LeagueSettings — singleton (id=1) (§4.2 + §4.5.2 state machine).
 *
 * @property int    $id
 * @property int    $team_count
 * @property int    $current_week
 * @property int    $total_weeks
 * @property ?int   $seed
 * @property string $status   'idle' | 'running' | 'resetting' | 'finished'
 * @property ?\Carbon\Carbon $status_updated_at
 */
class LeagueSettings extends Model
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_RESETTING = 'resetting';
    public const STATUS_FINISHED = 'finished';

    public const STATUSES = [
        self::STATUS_IDLE,
        self::STATUS_RUNNING,
        self::STATUS_RESETTING,
        self::STATUS_FINISHED,
    ];

    protected $table = 'league_settings';

    protected $fillable = [
        'team_count', 'current_week', 'total_weeks', 'seed', 'status', 'status_updated_at',
    ];

    protected $casts = [
        'team_count' => 'integer',
        'current_week' => 'integer',
        'total_weeks' => 'integer',
        'seed' => 'integer',
        'status_updated_at' => 'datetime',
    ];

    /**
     * Returns the singleton row, locking it for the current transaction
     * (PostgreSQL/MySQL: SELECT ... FOR UPDATE; SQLite: BEGIN IMMEDIATE expected
     * via caller — see §4.5.3).
     */
    public static function singleton(): self
    {
        $id = (int) config('league.settings_id', 1);
        return self::query()->lockForUpdate()->findOrFail($id);
    }

    public function isSeasonOver(): bool
    {
        return $this->current_week >= $this->total_weeks;
    }
}
