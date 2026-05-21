<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\MatchSimulator;
use App\Domain\Support\SeededRandom;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MatchSimulator tests — uses Team model (DB-backed) so we extend Laravel TestCase.
 *
 * Covers:
 *  - US-C-04 seed determinism
 *  - US-C-05 weak team can win at least once in 10k trials (small-scale variant
 *    uses 1k trials in fast suite; @group slow runs full 10k).
 */
final class MatchSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_determinism(): void
    {
        $home = Team::query()->create(['name' => 'A', 'power' => 80, 'supporter' => 70, 'keeper' => 70]);
        $away = Team::query()->create(['name' => 'B', 'power' => 80, 'supporter' => 70, 'keeper' => 70]);
        $sim = new MatchSimulator();
        $r1 = $sim->simulate($home, $away, 42);
        $r2 = $sim->simulate($home, $away, 42);
        $this->assertSame($r1, $r2, 'Same seed must produce identical score');
    }

    public function test_weak_team_can_win_within_1000_trials(): void
    {
        $strong = Team::query()->create(['name' => 'Strong', 'power' => 100, 'supporter' => 80, 'keeper' => 80]);
        $weak = Team::query()->create(['name' => 'Weak', 'power' => 10, 'supporter' => 30, 'keeper' => 30]);
        $sim = new MatchSimulator();

        $weakWins = 0;
        $strongWins = 0;
        for ($i = 0; $i < 1000; $i++) {
            $subseed = SeededRandom::deriveSubseed(42, 1, $i);
            $r = $sim->simulate($strong, $weak, $subseed);
            if ($r['home_score'] > $r['away_score']) $strongWins++;
            elseif ($r['away_score'] > $r['home_score']) $weakWins++;
        }
        // FAQ Q5: weak team must have a non-zero chance.
        $this->assertGreaterThan(0, $weakWins, 'Weak team must win at least once in 1k trials');
        $this->assertGreaterThan($weakWins, $strongWins, 'Strong team must win significantly more often');
    }

    /**
     * @group slow
     */
    public function test_weak_team_can_win_in_10k_trials(): void
    {
        $strong = Team::query()->create(['name' => 'Strong', 'power' => 100, 'supporter' => 80, 'keeper' => 80]);
        $weak = Team::query()->create(['name' => 'Weak', 'power' => 10, 'supporter' => 30, 'keeper' => 30]);
        $sim = new MatchSimulator();

        $weakWins = 0;
        for ($i = 0; $i < 10000; $i++) {
            $subseed = SeededRandom::deriveSubseed(42, 1, $i);
            $r = $sim->simulate($strong, $weak, $subseed);
            if ($r['away_score'] > $r['home_score']) $weakWins++;
        }
        $this->assertGreaterThan(0, $weakWins);
    }

    public function test_score_bounds_respected(): void
    {
        $home = Team::query()->create(['name' => 'A', 'power' => 100, 'supporter' => 100, 'keeper' => 100]);
        $away = Team::query()->create(['name' => 'B', 'power' => 100, 'supporter' => 100, 'keeper' => 100]);
        $sim = new MatchSimulator();
        for ($i = 0; $i < 200; $i++) {
            $r = $sim->simulate($home, $away, $i + 100);
            $this->assertGreaterThanOrEqual(0, $r['home_score']);
            $this->assertGreaterThanOrEqual(0, $r['away_score']);
            $this->assertLessThanOrEqual(20, $r['home_score']);
            $this->assertLessThanOrEqual(20, $r['away_score']);
        }
    }
}
