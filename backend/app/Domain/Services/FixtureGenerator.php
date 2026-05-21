<?php

namespace App\Domain\Services;

use App\Domain\Support\SeededRandom;
use InvalidArgumentException;

/**
 * FixtureGenerator — double round-robin via the circle method.
 *
 * - For N teams (N >= 2): 2*(N-1) weeks, N*(N-1) matches total.
 * - For odd N: a "bye" team is appended (one team sits out per week).
 * - Deterministic when a seed is provided (US-B-02). The seed only influences
 *   the initial team order (so two identical seeds yield identical fixtures);
 *   the circle method itself is deterministic.
 * - Flexibility (US-B-03 / NFR-10): no hard-coded team count — derives all
 *   sizes from len($teams).
 *
 * Output: array of weeks, each week is array of [home_id, away_id] pairs.
 *
 * @phpstan-type FixturePair array{home_team_id:int, away_team_id:int}
 * @phpstan-type FixtureWeek array<int, FixturePair>
 */
final class FixtureGenerator
{
    /**
     * @param array<int, int> $teamIds  Team IDs in the league (>= 2).
     * @param ?int            $seed     Optional reproducibility seed.
     * @return array<int, array<int, array{home_team_id:int, away_team_id:int}>>
     *         Indexed 1..2*(N-1) (1-based week numbers).
     */
    public function generate(array $teamIds, ?int $seed = null): array
    {
        $n = count($teamIds);
        if ($n < 2) {
            throw new InvalidArgumentException('At least 2 teams required to generate fixtures.');
        }

        $orderedTeams = array_values($teamIds);

        // Deterministic shuffle if a seed is provided (Fisher–Yates with SeededRandom).
        if ($seed !== null) {
            $rng = new SeededRandom($seed);
            for ($i = count($orderedTeams) - 1; $i > 0; $i--) {
                $j = $rng->nextInt(0, $i);
                if ($i !== $j) {
                    [$orderedTeams[$i], $orderedTeams[$j]] = [$orderedTeams[$j], $orderedTeams[$i]];
                }
            }
        }

        $isOdd = ($n % 2) === 1;
        $byeId = -1;
        if ($isOdd) {
            $orderedTeams[] = $byeId; // virtual bye team
        }

        $size = count($orderedTeams);          // always even after possible bye
        $weeksPerRound = $size - 1;            // single round-robin weeks
        $half = $size / 2;

        $firstHalf = [];

        // Circle method: pin position 0, rotate the rest.
        $arr = $orderedTeams;
        for ($w = 0; $w < $weeksPerRound; $w++) {
            $weekPairs = [];
            for ($i = 0; $i < $half; $i++) {
                $a = $arr[$i];
                $b = $arr[$size - 1 - $i];
                if ($a === $byeId || $b === $byeId) {
                    continue;
                }
                // Alternate home/away each pairing to balance.
                if ($i === 0) {
                    // pinned slot: even weeks home/away to balance overall.
                    if ($w % 2 === 0) {
                        $weekPairs[] = ['home_team_id' => $a, 'away_team_id' => $b];
                    } else {
                        $weekPairs[] = ['home_team_id' => $b, 'away_team_id' => $a];
                    }
                } else {
                    $weekPairs[] = ['home_team_id' => $a, 'away_team_id' => $b];
                }
            }
            $firstHalf[] = $weekPairs;

            // Rotate: keep arr[0] fixed, rotate arr[1..size-1] clockwise.
            $last = array_pop($arr);
            array_splice($arr, 1, 0, [$last]);
        }

        // Second half = first half mirrored (swap home/away), in the same week order.
        $secondHalf = [];
        foreach ($firstHalf as $week) {
            $mirrored = [];
            foreach ($week as $pair) {
                $mirrored[] = [
                    'home_team_id' => $pair['away_team_id'],
                    'away_team_id' => $pair['home_team_id'],
                ];
            }
            $secondHalf[] = $mirrored;
        }

        // Concatenate and re-index as 1..2*(N-1).
        $allWeeks = array_merge($firstHalf, $secondHalf);
        $out = [];
        foreach ($allWeeks as $i => $week) {
            $out[$i + 1] = $week;
        }
        return $out;
    }
}
