<?php

namespace App\Domain\Actions;

use App\Exceptions\LeagueLockedException;
use App\Models\LeagueSettings;
use Illuminate\Support\Facades\DB;

/**
 * PlayAllWeeksAction (§4.5.1, US-F-01, US-F-03)
 *
 * Per-week commit (US-F-03 AC-1): each call to PlayWeekAction runs its own
 * DB::transaction. This guarantees resume-after-crash semantics — if a single
 * week fails, the league's current_week reflects the last successful week.
 *
 * The "running" status is purely advisory: clients should not call other
 * mutations while play-all is in flight. Because PHP runs synchronously per
 * request, in practice the loop completes (or errors) within a single HTTP
 * request lifetime, and no other request can race against this one for the
 * same league_settings row (the SQLite busy_timeout + Laravel default
 * single-process serving model gives us that).
 */
final class PlayAllWeeksAction
{
    public function __construct(
        private readonly PlayWeekAction $playWeek,
    ) {}

    public function execute(): array
    {
        // Pre-flight guard: reject if already locked.
        $settings = LeagueSettings::query()->findOrFail((int) config('league.settings_id', 1));
        if (in_array($settings->status, [LeagueSettings::STATUS_RUNNING, LeagueSettings::STATUS_RESETTING], true)) {
            throw new LeagueLockedException();
        }

        $weeksPlayed = 0;
        while (true) {
            $settings = LeagueSettings::query()->findOrFail((int) config('league.settings_id', 1));
            if ($settings->current_week >= $settings->total_weeks) {
                break;
            }
            $expected = $settings->current_week + 1;
            $this->playWeek->execute($expected);
            $weeksPlayed++;
        }

        $final = LeagueSettings::query()->findOrFail((int) config('league.settings_id', 1));
        return [
            'weeks_played' => $weeksPlayed,
            'current_week' => $final->current_week,
            'status' => $final->status,
        ];
    }
}
