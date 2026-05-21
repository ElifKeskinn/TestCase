<?php

namespace Tests\Unit\Domain;

use App\Domain\Services\FixtureGenerator;
use PHPUnit\Framework\TestCase;

/**
 * FixtureGenerator unit tests (US-B-01, US-B-02, US-B-03).
 *
 * Pure-domain — does not boot Laravel.
 */
final class FixtureGeneratorTest extends TestCase
{
    public function test_four_teams_double_round_robin(): void
    {
        $gen = new FixtureGenerator();
        $teams = [1, 2, 3, 4];
        $weeks = $gen->generate($teams);

        // 2 * (N-1) = 6 weeks
        $this->assertCount(6, $weeks, '4 teams must produce 6 weeks');

        // N * (N-1) = 12 matches
        $totalMatches = 0;
        foreach ($weeks as $w) {
            $totalMatches += count($w);
        }
        $this->assertSame(12, $totalMatches, '4 teams must produce 12 matches total');

        // Each team plays exactly once per week
        foreach ($weeks as $weekIdx => $week) {
            $appearances = [];
            foreach ($week as $pair) {
                $appearances[$pair['home_team_id']] = ($appearances[$pair['home_team_id']] ?? 0) + 1;
                $appearances[$pair['away_team_id']] = ($appearances[$pair['away_team_id']] ?? 0) + 1;
            }
            foreach ($teams as $t) {
                $this->assertSame(1, $appearances[$t] ?? 0, "Team {$t} must play exactly once in week {$weekIdx}");
            }
        }

        // Each pair meets exactly twice (home + away)
        $pairCount = [];
        foreach ($weeks as $week) {
            foreach ($week as $pair) {
                $key = $pair['home_team_id'].'-'.$pair['away_team_id'];
                $pairCount[$key] = ($pairCount[$key] ?? 0) + 1;
            }
        }
        foreach ($teams as $a) {
            foreach ($teams as $b) {
                if ($a === $b) continue;
                $forward = ($pairCount["{$a}-{$b}"] ?? 0);
                $this->assertSame(
                    1, $forward,
                    "Pair {$a}-{$b} must occur exactly once with that home/away orientation"
                );
            }
        }
    }

    public function test_six_teams(): void
    {
        $gen = new FixtureGenerator();
        $weeks = $gen->generate([1,2,3,4,5,6]);
        $this->assertCount(10, $weeks);

        $total = 0;
        foreach ($weeks as $w) $total += count($w);
        $this->assertSame(30, $total);
    }

    public function test_eight_teams(): void
    {
        $gen = new FixtureGenerator();
        $weeks = $gen->generate([1,2,3,4,5,6,7,8]);
        $this->assertCount(14, $weeks);

        $total = 0;
        foreach ($weeks as $w) $total += count($w);
        $this->assertSame(56, $total);
    }

    public function test_seed_reproducibility(): void
    {
        $gen = new FixtureGenerator();
        $a = $gen->generate([1,2,3,4], seed: 42);
        $b = $gen->generate([1,2,3,4], seed: 42);
        $this->assertSame($a, $b, 'Same seed must yield identical fixtures');
    }

    public function test_invalid_team_count_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FixtureGenerator())->generate([1]);
    }

    /**
     * US-B-03 (spec section 4.5.3) / R-04: tek sayi takim icin "bye" destegi.
     *
     * Matematik (N=5 odd):
     *   Effective N = N+1 = 6 (one virtual bye team).
     *   Single round-robin = N-1 = 5 weeks per team.
     *   Double round-robin = 2 * 5 = 10 weeks total.
     *   Toplam mac sayisi = N*(N-1) = 5*4 = 20.
     */
    public function test_odd_teams_get_bye_no_self_pairs(): void
    {
        $gen = new FixtureGenerator();
        $teams = [1, 2, 3, 4, 5];
        $weeks = $gen->generate($teams);

        // 1) Total weeks = 2 * (effective N - 1) = 2 * 5 = 10.
        $this->assertCount(10, $weeks, 'N=5 odd should produce 10 weeks via bye dummy');

        // 2) No virtual team id (-1) leaks; no self-pairs.
        foreach ($weeks as $week) {
            foreach ($week as $pair) {
                $this->assertNotSame(-1, $pair['home_team_id']);
                $this->assertNotSame(-1, $pair['away_team_id']);
                $this->assertNotSame($pair['home_team_id'], $pair['away_team_id']);
            }
        }

        // 3) Total matches = N * (N - 1) = 20.
        $totalMatches = 0;
        foreach ($weeks as $w) $totalMatches += count($w);
        $this->assertSame(20, $totalMatches, 'N=5 odd should produce 20 matches');

        // 4) Each team plays (N-1)=4 home and 4 away matches.
        $homeCounts = array_fill_keys($teams, 0);
        $awayCounts = array_fill_keys($teams, 0);
        foreach ($weeks as $week) {
            foreach ($week as $pair) {
                $homeCounts[$pair['home_team_id']]++;
                $awayCounts[$pair['away_team_id']]++;
            }
        }
        foreach ($teams as $t) {
            $this->assertSame(4, $homeCounts[$t], "Team {$t} must play 4 home matches");
            $this->assertSame(4, $awayCounts[$t], "Team {$t} must play 4 away matches");
        }

        // 5) Each team plays at most once per week (bye weeks allowed).
        foreach ($weeks as $weekIdx => $week) {
            $appearances = array_fill_keys($teams, 0);
            foreach ($week as $pair) {
                $appearances[$pair['home_team_id']]++;
                $appearances[$pair['away_team_id']]++;
            }
            foreach ($teams as $t) {
                $this->assertLessThanOrEqual(
                    1,
                    $appearances[$t],
                    "Team {$t} must play at most once in week {$weekIdx}"
                );
            }
        }

        // 6) Each unordered pair meets exactly twice (home + away).
        $pairTotals = [];
        foreach ($weeks as $week) {
            foreach ($week as $pair) {
                $a = $pair['home_team_id'];
                $b = $pair['away_team_id'];
                $key = ($a < $b) ? "{$a}-{$b}" : "{$b}-{$a}";
                $pairTotals[$key] = ($pairTotals[$key] ?? 0) + 1;
            }
        }
        foreach ($teams as $a) {
            foreach ($teams as $b) {
                if ($a >= $b) continue;
                $this->assertSame(2, $pairTotals["{$a}-{$b}"] ?? 0, "Pair {$a}-{$b} must meet exactly twice");
            }
        }
    }

    /** US-B-03 boundary: N=2 minimum -- 2 weeks (home+away), 2 matches, swapped roles. */
    public function test_two_teams_minimum_double_round_robin(): void
    {
        $gen = new FixtureGenerator();
        $weeks = $gen->generate([1, 2]);
        $this->assertCount(2, $weeks, '2 teams -> 2 weeks');
        $total = 0;
        foreach ($weeks as $w) $total += count($w);
        $this->assertSame(2, $total, '2 teams -> 2 matches');

        $matches = [];
        foreach ($weeks as $week) {
            foreach ($week as $p) $matches[] = [$p['home_team_id'], $p['away_team_id']];
        }
        $this->assertSame(
            [$matches[0][0], $matches[0][1]],
            [$matches[1][1], $matches[1][0]],
            'Second leg must reverse home/away of the first leg'
        );
    }
}