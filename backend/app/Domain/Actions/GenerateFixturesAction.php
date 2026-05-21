<?php

namespace App\Domain\Actions;

use App\Domain\Services\FixtureGenerator;
use App\Domain\Services\StandingsCalculator;
use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Prediction;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * GenerateFixturesAction (§4.5.1)
 *
 * - Single DB::transaction()
 * - Deletes existing fixtures, predictions, standings rows
 * - Generates fresh fixtures via FixtureGenerator
 * - Resets current_week = 0, total_weeks = 2*(N-1), status = 'idle'
 *
 * Idempotent? No (per spec): every call replaces the fixtures.
 */
final class GenerateFixturesAction
{
    public function __construct(
        private readonly FixtureGenerator $generator,
        private readonly StandingsCalculator $standings,
    ) {}

    public function execute(): array
    {
        return DB::transaction(function () {
            $settings = LeagueSettings::singleton();
            $this->guardMutable($settings);

            $teams = Team::query()->orderBy('id')->get();
            if ($teams->count() < 2) {
                throw new RuntimeException('Cannot generate fixtures with fewer than 2 teams.');
            }

            $teamIds = $teams->pluck('id')->all();

            // Yeni rasgele seed: her Generate Fixtures çağrısı farklı bir fikstür üretir.
            // Reproducibility için yine de seed `league_settings.seed`'e persist edilir,
            // böylece testlerde / debug'da aynı sezon tekrar üretilebilir.
            $settings->seed = random_int(1, PHP_INT_MAX);

            $weeks = $this->generator->generate($teamIds, $settings->seed);

            // Wipe existing fixtures + dependent snapshots.
            Prediction::query()->delete();
            MatchModel::query()->delete();
            Standing::query()->delete();

            // Insert new fixtures.
            $now = now();
            $rows = [];
            foreach ($weeks as $week => $pairs) {
                foreach ($pairs as $pair) {
                    $rows[] = [
                        'week' => $week,
                        'home_team_id' => $pair['home_team_id'],
                        'away_team_id' => $pair['away_team_id'],
                        'home_score' => null,
                        'away_score' => null,
                        'played_at' => null,
                        'version' => 1,
                        'editions_count' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            MatchModel::query()->insert($rows);

            // Recompute (will yield zeroed snapshot, but exercises invariants).
            $this->standings->recompute();

            // Reset league counters.
            $settings->current_week = 0;
            $settings->total_weeks = 2 * (count($teamIds) - 1);
            $settings->team_count = count($teamIds);
            $settings->status = LeagueSettings::STATUS_IDLE;
            $settings->status_updated_at = now();
            $settings->save();

            return [
                'team_count' => $settings->team_count,
                'total_weeks' => $settings->total_weeks,
                'matches_inserted' => count($rows),
            ];
        });
    }

    private function guardMutable(LeagueSettings $s): void
    {
        if (in_array($s->status, [LeagueSettings::STATUS_RUNNING, LeagueSettings::STATUS_RESETTING], true)) {
            throw new \App\Exceptions\LeagueLockedException();
        }
    }
}
