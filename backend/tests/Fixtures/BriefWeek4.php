<?php

namespace Tests\Fixtures;

use App\Models\Standing;
use Illuminate\Support\Carbon;

/**
 * Senaryo F-Test-3 (§9.6) — Brief PDF Week 4 figure.
 *
 * Chelsea PTS=10, P=4, W=3, D=1, L=0, GF=12, GA=1, GD=+11
 * Arsenal PTS=8, P=4, W=2, D=2, L=0, GF=8, GA=2, GD=+6
 * Manchester City PTS=8, P=4, W=2, D=2, L=0, GF=6, GA=2, GD=+4
 * Liverpool PTS=4, P=4, W=1, D=1, L=2, GF=4, GA=4, GD=0
 */
final class BriefWeek4
{
    /**
     * @param array<string, \App\Models\Team> $teams
     */
    public static function setup(array $teams): void
    {
        $now = Carbon::now();
        $rows = [
            ['team_id' => $teams['Chelsea']->id,         'played' => 4, 'won' => 3, 'drawn' => 1, 'lost' => 0, 'goals_for' => 12, 'goals_against' => 1, 'goal_diff' => 11, 'points' => 10],
            ['team_id' => $teams['Arsenal']->id,         'played' => 4, 'won' => 2, 'drawn' => 2, 'lost' => 0, 'goals_for' => 8,  'goals_against' => 2, 'goal_diff' => 6,  'points' => 8],
            ['team_id' => $teams['Manchester City']->id, 'played' => 4, 'won' => 2, 'drawn' => 2, 'lost' => 0, 'goals_for' => 6,  'goals_against' => 2, 'goal_diff' => 4,  'points' => 8],
            ['team_id' => $teams['Liverpool']->id,       'played' => 4, 'won' => 1, 'drawn' => 1, 'lost' => 2, 'goals_for' => 4,  'goals_against' => 4, 'goal_diff' => 0,  'points' => 4],
        ];
        foreach ($rows as $row) {
            Standing::query()->create($row + ['updated_at' => $now]);
        }
    }
}
