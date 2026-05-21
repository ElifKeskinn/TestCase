<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\StandingsCalculator;
use App\Models\MatchModel;
use App\Models\Standing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\TeamPowerProfiles;
use Tests\TestCase;

/**
 * Premier League 3/1/0 scoring (US-D-01) + invariants (P=W+D+L, GD=GF-GA, PTS=3W+D).
 */
final class StandingsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_one_zero_scoring(): void
    {
        $teams = TeamPowerProfiles::seed();
        // Liverpool beats Chelsea 2-1
        MatchModel::query()->create([
            'week' => 1, 'home_team_id' => $teams['Liverpool']->id, 'away_team_id' => $teams['Chelsea']->id,
            'home_score' => 2, 'away_score' => 1, 'played_at' => now(), 'version' => 2,
        ]);
        // Arsenal draws Manchester City 1-1
        MatchModel::query()->create([
            'week' => 1, 'home_team_id' => $teams['Arsenal']->id, 'away_team_id' => $teams['Manchester City']->id,
            'home_score' => 1, 'away_score' => 1, 'played_at' => now(), 'version' => 2,
        ]);

        (new StandingsCalculator())->recompute();

        $bynam = Standing::query()->with('team')->get()->keyBy(fn($s) => $s->team->name);
        $this->assertSame(3, $bynam['Liverpool']->points);
        $this->assertSame(0, $bynam['Chelsea']->points);
        $this->assertSame(1, $bynam['Arsenal']->points);
        $this->assertSame(1, $bynam['Manchester City']->points);
    }

    public function test_invariants_hold(): void
    {
        $teams = TeamPowerProfiles::seed();
        MatchModel::query()->create([
            'week' => 1, 'home_team_id' => $teams['Liverpool']->id, 'away_team_id' => $teams['Chelsea']->id,
            'home_score' => 3, 'away_score' => 1, 'played_at' => now(), 'version' => 2,
        ]);
        MatchModel::query()->create([
            'week' => 1, 'home_team_id' => $teams['Arsenal']->id, 'away_team_id' => $teams['Manchester City']->id,
            'home_score' => 0, 'away_score' => 2, 'played_at' => now(), 'version' => 2,
        ]);
        (new StandingsCalculator())->recompute();

        foreach (Standing::query()->get() as $s) {
            $this->assertSame($s->won + $s->drawn + $s->lost, $s->played, 'P = W+D+L');
            $this->assertSame($s->goals_for - $s->goals_against, $s->goal_diff, 'GD = GF-GA');
            $this->assertSame(3 * $s->won + $s->drawn, $s->points, 'PTS = 3W+D');
        }
    }
}
