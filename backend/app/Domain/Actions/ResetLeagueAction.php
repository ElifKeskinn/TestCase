<?php

namespace App\Domain\Actions;

use App\Exceptions\LeagueLockedException;
use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Prediction;
use App\Models\Standing;
use Illuminate\Support\Facades\DB;

/**
 * ResetLeagueAction (§4.5.1, US-A-03)
 *
 * - Single DB::transaction()
 * - DELETE FROM matches / standings / predictions
 * - UPDATE league_settings SET current_week=0, status='idle'
 *
 * State machine: any status -> 'resetting' -> 'idle'. Reset is allowed from
 * 'idle' and 'finished'; rejected when 'running' or already 'resetting'.
 */
final class ResetLeagueAction
{
    public function execute(): array
    {
        return DB::transaction(function () {
            $settings = LeagueSettings::singleton();

            if ($settings->status === LeagueSettings::STATUS_RUNNING || $settings->status === LeagueSettings::STATUS_RESETTING) {
                throw new LeagueLockedException();
            }

            $settings->status = LeagueSettings::STATUS_RESETTING;
            $settings->status_updated_at = now();
            $settings->save();

            Prediction::query()->delete();
            MatchModel::query()->delete();
            Standing::query()->delete();

            $settings->current_week = 0;
            $settings->seed = null;
            $settings->status = LeagueSettings::STATUS_IDLE;
            $settings->status_updated_at = now();
            $settings->save();

            return [
                'status' => $settings->status,
                'current_week' => $settings->current_week,
            ];
        });
    }
}
