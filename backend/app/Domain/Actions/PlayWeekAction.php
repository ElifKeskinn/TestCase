<?php

namespace App\Domain\Actions;

use App\Domain\Services\ChampionshipPredictor;
use App\Domain\Services\MatchSimulator;
use App\Domain\Services\StandingsCalculator;
use App\Domain\Support\SeededRandom;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\LeagueLockedException;
use App\Models\LeagueSettings;
use App\Models\MatchModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PlayWeekAction (§4.5.1, §4.5.5, US-F-03)
 *
 * - Single DB::transaction()
 * - Validates expected_week (§4.5.5) → 409 on mismatch
 * - Status guard: rejects when 'running' or 'resetting' → 423 Locked
 * - Order: matches → standings → predictions → current_week++ (§4.5.7)
 * - Subseed per match: hash(seed, match.id, week) (§4.5.9)
 */
final class PlayWeekAction
{
    public function __construct(
        private readonly MatchSimulator $simulator,
        private readonly StandingsCalculator $standings,
        private readonly ChampionshipPredictor $predictor,
    ) {}

    public function execute(int $expectedWeek): array
    {
        return DB::transaction(function () use ($expectedWeek) {
            $settings = LeagueSettings::singleton();
            $this->guardMutable($settings);

            $nextWeek = $settings->current_week + 1;
            if ($nextWeek !== $expectedWeek) {
                throw new IdempotencyConflictException(
                    "expected_week mismatch: server expected={$nextWeek}, got={$expectedWeek}"
                );
            }
            if ($nextWeek > $settings->total_weeks) {
                throw new IdempotencyConflictException('Season already finished.');
            }

            $weekMatches = MatchModel::query()
                ->where('week', $nextWeek)
                ->whereNull('home_score')
                ->get();

            $now = Carbon::now();
            $seedBase = (int) ($settings->seed ?? config('league.default_seed', 42));

            foreach ($weekMatches as $m) {
                $subseed = SeededRandom::deriveSubseed($seedBase, (int) $m->id, $nextWeek);
                $home = $m->homeTeam;
                $away = $m->awayTeam;
                $result = $this->simulator->simulate($home, $away, $subseed);

                $m->home_score = $result['home_score'];
                $m->away_score = $result['away_score'];
                $m->played_at = $now;
                $m->version = $m->version + 1;
                $m->save();
            }

            // 2) Recompute standings (full recompute from matches — idempotent).
            $this->standings->recompute();

            // 3) Advance current_week BEFORE predicting so the trigger formula uses fresh value.
            $settings->current_week = $nextWeek;
            $settings->status_updated_at = now();
            if ($nextWeek === $settings->total_weeks) {
                $settings->status = LeagueSettings::STATUS_FINISHED;
            }
            $settings->save();

            // 4) Predictions (only if trigger condition met).
            $this->predictor->predictAndSnapshot($settings);

            return [
                'week_played' => $nextWeek,
                'matches' => $weekMatches->count(),
                'current_week' => $settings->current_week,
                'status' => $settings->status,
            ];
        });
    }

    private function guardMutable(LeagueSettings $s): void
    {
        if (in_array($s->status, [LeagueSettings::STATUS_RUNNING, LeagueSettings::STATUS_RESETTING], true)) {
            throw new LeagueLockedException();
        }
    }
}
