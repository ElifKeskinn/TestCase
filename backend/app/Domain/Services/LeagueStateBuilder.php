<?php

namespace App\Domain\Services;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Prediction;
use App\Models\Standing;
use App\Models\Team;

/**
 * LeagueStateBuilder — assembles the full LeagueState envelope consumed by the
 * frontend (see frontend/src/types/league.ts::LeagueState).
 *
 * Every mutation endpoint (generate-fixtures, play-next-week, play-all-weeks,
 * reset, PATCH /matches/{id}) returns this envelope so the SPA can call
 * `applyState(...)` once and replace its entire reactive store atomically.
 *
 * Shape (mirrors the TypeScript contract exactly):
 *   {
 *     settings:    { id, team_count, current_week, total_weeks, seed, status },
 *     teams:       Team[],
 *     matches:     Match[]   // FLAT, ordered by week then id
 *     standings:   Standing[] with `team_name` (NOT `name`)
 *     predictions: Prediction[] // ARRAY, not a {team_id => pct} map
 *   }
 */
final class LeagueStateBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $settings = LeagueSettings::query()->find((int) config('league.settings_id', 1));

        $teams = Team::query()->orderBy('name')->get();

        $matches = MatchModel::query()
            ->orderBy('week')
            ->orderBy('id')
            ->get();

        $standings = Standing::query()
            ->with('team')
            ->orderByDesc('points')
            ->orderByDesc('goal_diff')
            ->orderByDesc('goals_for')
            ->orderBy('team_id')
            ->get();

        $predictions = collect();
        if ($settings !== null) {
            $predictions = Prediction::query()
                ->with('team')
                ->where('week', $settings->current_week)
                ->orderBy('team_id')
                ->get();
        }

        return [
            'settings' => $settings ? [
                'id' => $settings->id,
                'team_count' => $settings->team_count,
                'current_week' => $settings->current_week,
                'total_weeks' => $settings->total_weeks,
                'seed' => $settings->seed,
                'status' => $settings->status,
            ] : null,

            'teams' => $teams->map(fn(Team $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'power' => $t->power,
                'supporter' => $t->supporter,
                'keeper' => $t->keeper,
            ])->values(),

            'matches' => $matches->map(fn(MatchModel $m) => [
                'id' => $m->id,
                'week' => $m->week,
                'home_team_id' => $m->home_team_id,
                'away_team_id' => $m->away_team_id,
                'home_score' => $m->home_score,
                'away_score' => $m->away_score,
                'played_at' => $m->played_at,
                'version' => $m->version,
                'editions_count' => $m->editions_count,
            ])->values(),

            'standings' => $standings->map(fn(Standing $s) => [
                'team_id' => $s->team_id,
                'team_name' => $s->team?->name,
                'played' => $s->played,
                'won' => $s->won,
                'drawn' => $s->drawn,
                'lost' => $s->lost,
                'goals_for' => $s->goals_for,
                'goals_against' => $s->goals_against,
                'goal_diff' => $s->goal_diff,
                'points' => $s->points,
            ])->values(),

            'predictions' => $predictions->map(fn(Prediction $p) => [
                'team_id' => $p->team_id,
                'team_name' => $p->team?->name,
                'week' => $p->week,
                'champion_probability' => (float) $p->champion_probability,
            ])->values(),
        ];
    }
}
