<?php

namespace App\Domain\Services;

use App\Domain\Support\SeededRandom;
use App\Domain\Tiebreak\StandingsSorter;
use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Prediction;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * ChampionshipPredictor — Monte Carlo (N=10.000, NFR-02) with Strategy seam.
 *
 * Trigger condition (US-E-01, jenerik formül):
 *     currentWeek > totalWeeks - prediction_window  (default window = 3)
 *
 * Algorithm:
 *  1. Compute remaining matches (home_score IS NULL).
 *  2. Run N Monte Carlo trials:
 *      - For each remaining match, sample (home_goals, away_goals) via MatchSimulator
 *        using a sub-seed derived from (league.seed, match.id, trial).
 *      - Apply Premier League 3/1/0 scoring on top of current standings.
 *      - Apply tiebreak chain (StandingsSorter).
 *      - Count champions (rank 1) per team.
 *  3. Normalize to decimal(5,2) percentages and force sum == 100.00 via
 *     **largest remainder method** (US-E-02).
 *
 * Upserts results into the `predictions` table for the current week.
 */
final class ChampionshipPredictor
{
    public function __construct(
        private readonly MatchSimulator $simulator,
    ) {}

    public function isTriggered(LeagueSettings $settings): bool
    {
        $window = (int) config('league.prediction_window', 3);
        return $settings->current_week > $settings->total_weeks - $window;
    }

    /**
     * Compute championship probabilities and snapshot them for the current week.
     * Returns an array of [team_id => percentage].
     *
     * @return array<int, float>
     */
    public function predictAndSnapshot(LeagueSettings $settings): array
    {
        if (!$this->isTriggered($settings)) {
            return [];
        }

        $teams = Team::query()->get(['id', 'name', 'power', 'supporter', 'keeper']);
        $standings = Standing::query()->get()->keyBy('team_id');

        $teamMap = [];
        foreach ($teams as $t) {
            $teamMap[$t->id] = $t;
        }

        // Snapshot current totals (mutable per-trial copies are made below).
        $baseRows = [];
        foreach ($teams as $t) {
            $s = $standings->get($t->id);
            $baseRows[$t->id] = [
                'team_id' => $t->id,
                'name' => $t->name,
                'points' => $s ? (int) $s->points : 0,
                'won' => $s ? (int) $s->won : 0,
                'drawn' => $s ? (int) $s->drawn : 0,
                'lost' => $s ? (int) $s->lost : 0,
                'goals_for' => $s ? (int) $s->goals_for : 0,
                'goals_against' => $s ? (int) $s->goals_against : 0,
                'goal_diff' => $s ? (int) $s->goal_diff : 0,
            ];
        }

        $remaining = MatchModel::query()
            ->whereNull('home_score')
            ->whereNull('away_score')
            ->orderBy('week')->orderBy('id')
            ->get();

        $runs = (int) config('league.monte_carlo_runs', 10000);
        $seedBase = (int) ($settings->seed ?? config('league.default_seed', 42));

        $championCounts = array_fill_keys(array_keys($baseRows), 0);

        // Early exit — uncatchable leader (FAQ Example A).
        // If no team can mathematically catch the current leader given remaining matches,
        // assign 100% to the leader, 0% to others.
        $leaderId = $this->detectUncatchableLeader($baseRows, $remaining);
        if ($leaderId !== null) {
            foreach (array_keys($championCounts) as $tid) {
                $championCounts[$tid] = ($tid === $leaderId) ? $runs : 0;
            }
        } else {
            for ($trial = 0; $trial < $runs; $trial++) {
                $sim = $baseRows; // shallow copy
                foreach ($remaining as $m) {
                    $subseed = SeededRandom::deriveSubseed($seedBase, (int) $m->id, $trial);
                    $home = $teamMap[$m->home_team_id] ?? null;
                    $away = $teamMap[$m->away_team_id] ?? null;
                    if ($home === null || $away === null) {
                        continue;
                    }
                    $result = $this->simulator->simulate($home, $away, $subseed);
                    $this->applyResult($sim, $m->home_team_id, $m->away_team_id, $result['home_score'], $result['away_score']);
                }
                $sorted = StandingsSorter::sort(array_values($sim));
                $championCounts[$sorted[0]['team_id']]++;
            }
        }

        $percentages = $this->normalizeWithLargestRemainder($championCounts, $runs);

        // Persist snapshot for this week.
        $now = Carbon::now();
        $rows = [];
        foreach ($percentages as $teamId => $pct) {
            $rows[] = [
                'team_id' => $teamId,
                'week' => $settings->current_week,
                'champion_probability' => $pct,
                'computed_at' => $now,
            ];
        }
        Prediction::query()->upsert(
            $rows,
            ['team_id', 'week'],
            ['champion_probability', 'computed_at']
        );

        return $percentages;
    }

    /**
     * Detect if the current leader is mathematically uncatchable
     * given the remaining matches (FAQ Example A short-circuit).
     *
     * Returns leader team_id if uncatchable, otherwise null.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param iterable<MatchModel> $remaining
     */
    private function detectUncatchableLeader(array $rows, iterable $remaining): ?int
    {
        if (count($rows) < 2) {
            return null;
        }
        $sorted = StandingsSorter::sort(array_values($rows));
        $leader = $sorted[0];
        $leaderId = (int) $leader['team_id'];
        $leaderPts = (int) $leader['points'];

        // Compute the max additional points each team can still earn.
        $maxGain = array_fill_keys(array_keys($rows), 0);
        foreach ($remaining as $m) {
            $maxGain[$m->home_team_id] = ($maxGain[$m->home_team_id] ?? 0) + 3;
            $maxGain[$m->away_team_id] = ($maxGain[$m->away_team_id] ?? 0) + 3;
        }

        foreach ($rows as $teamId => $row) {
            if ($teamId === $leaderId) {
                continue;
            }
            $maxReachable = (int) $row['points'] + ($maxGain[$teamId] ?? 0);
            if ($maxReachable > $leaderPts) {
                return null; // someone can still catch
            }
            // Tie on points possible — defer to MC.
            if ($maxReachable === $leaderPts) {
                return null;
            }
        }

        return $leaderId;
    }

    /**
     * Apply a single simulated result to the in-memory standings copy.
     *
     * @param array<int, array<string, mixed>> $rows  (mutated by reference)
     */
    private function applyResult(array &$rows, int $homeId, int $awayId, int $homeScore, int $awayScore): void
    {
        if (!isset($rows[$homeId]) || !isset($rows[$awayId])) {
            return;
        }

        $rows[$homeId]['goals_for'] += $homeScore;
        $rows[$homeId]['goals_against'] += $awayScore;
        $rows[$awayId]['goals_for'] += $awayScore;
        $rows[$awayId]['goals_against'] += $homeScore;

        if ($homeScore > $awayScore) {
            $rows[$homeId]['won']++;
            $rows[$awayId]['lost']++;
            $rows[$homeId]['points'] += 3;
        } elseif ($homeScore < $awayScore) {
            $rows[$awayId]['won']++;
            $rows[$homeId]['lost']++;
            $rows[$awayId]['points'] += 3;
        } else {
            $rows[$homeId]['drawn']++;
            $rows[$awayId]['drawn']++;
            $rows[$homeId]['points']++;
            $rows[$awayId]['points']++;
        }
        $rows[$homeId]['goal_diff'] = $rows[$homeId]['goals_for'] - $rows[$homeId]['goals_against'];
        $rows[$awayId]['goal_diff'] = $rows[$awayId]['goals_for'] - $rows[$awayId]['goals_against'];
    }

    /**
     * Largest-remainder method → percentages with sum == 100.00 (exact).
     *
     * @param array<int, int> $counts  team_id => champion_trials
     * @param int             $total   total trials
     * @return array<int, float>       team_id => percentage with 2 decimals
     */
    public function normalizeWithLargestRemainder(array $counts, int $total): array
    {
        if ($total <= 0 || array_sum($counts) === 0) {
            return array_map(fn() => 0.00, $counts);
        }

        // Integer-arithmetic largest remainder method.
        // We work in units of 0.01% (target = 10_000 "hundredths-of-a-percent")
        // to preserve decimal(5,2) precision without float drift.
        $target = 10000;
        $floors = [];
        $remainders = [];
        $sumFloor = 0;
        foreach ($counts as $teamId => $c) {
            // integer quotient: floor((c * target) / total)
            $num = $c * $target;
            $q = intdiv($num, $total);
            $r = $num - ($q * $total); // exact integer remainder
            $floors[$teamId] = $q;
            $remainders[$teamId] = $r;
            $sumFloor += $q;
        }

        $missing = $target - $sumFloor;
        if ($missing > 0) {
            // Sort by remainder desc, then team_id asc for determinism.
            $keys = array_keys($remainders);
            usort($keys, function ($a, $b) use ($remainders) {
                $cmp = $remainders[$b] <=> $remainders[$a];
                if ($cmp !== 0) {
                    return $cmp;
                }
                return $a <=> $b;
            });
            for ($i = 0; $i < $missing && $i < count($keys); $i++) {
                $floors[$keys[$i]]++;
            }
        }

        // Build percentages. To guarantee array_sum(out) === 100.0 exactly
        // (immune to IEEE-754 drift from values like 33.30 whose binary
        // representation is not exact), we identify an "absorber" entry
        // (the largest float by hundredths, ties → smallest team_id)
        // and compute its value as 100.0 minus the running sum of the
        // others — using the SAME left-to-right accumulator order that
        // array_sum() will use, so the final fold lands on exactly 100.0.
        $out = [];
        foreach ($floors as $teamId => $hundredths) {
            $out[$teamId] = $hundredths / 100.0;
        }
        if (count($out) > 0) {
            // Pick absorber: max hundredths, then min team_id for determinism.
            $absorbKey = array_key_first($floors);
            foreach ($floors as $teamId => $hundredths) {
                if (
                    $hundredths > $floors[$absorbKey]
                    || ($hundredths === $floors[$absorbKey] && $teamId < $absorbKey)
                ) {
                    $absorbKey = $teamId;
                }
            }
            // Reorder so the absorber is last — matches array_sum() iteration order.
            $ordered = [];
            foreach ($out as $teamId => $v) {
                if ($teamId !== $absorbKey) {
                    $ordered[$teamId] = $v;
                }
            }
            // Simulate array_sum's left-fold over the non-absorber prefix.
            $prefix = 0.0;
            foreach ($ordered as $v) {
                $prefix = $prefix + $v;
            }
            // Set absorber = 100.0 - prefix. By Sterbenz' lemma the subtraction
            // is exact when prefix is in [50, 100], which holds whenever the
            // absorber is the largest share and total counts > 0.
            $ordered[$absorbKey] = 100.0 - $prefix;
            $out = $ordered;
        }
        return $out;
    }
}
