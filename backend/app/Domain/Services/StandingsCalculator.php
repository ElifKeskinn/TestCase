<?php

namespace App\Domain\Services;

use App\Models\MatchModel;
use App\Models\Standing;
use App\Models\Team;
use Illuminate\Support\Carbon;

/**
 * StandingsCalculator — derived state, recomputed-from-scratch.
 *
 * Invariants enforced by both DB CHECK and this calculator:
 *   - played = won + drawn + lost
 *   - goal_diff = goals_for - goals_against
 *   - points = 3*won + drawn (Premier League 3/1/0, FAQ Q1).
 *
 * Always reads the full matches set and writes the entire standings snapshot
 * (no incremental delta updates) — idempotent and crash-safe.
 */
final class StandingsCalculator
{
    public function recompute(): void
    {
        $teams = Team::query()->get(['id', 'name'])->keyBy('id');
        $now = Carbon::now();

        // Initialize rows with zeros.
        $rows = [];
        foreach ($teams as $team) {
            $rows[$team->id] = [
                'team_id' => $team->id,
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                'goals_for' => 0, 'goals_against' => 0,
                'goal_diff' => 0, 'points' => 0,
                'updated_at' => $now,
            ];
        }

        // Aggregate from played matches.
        $matches = MatchModel::query()
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        foreach ($matches as $m) {
            $hid = $m->home_team_id;
            $aid = $m->away_team_id;
            if (!isset($rows[$hid]) || !isset($rows[$aid])) {
                continue; // safety guard
            }

            $hs = (int) $m->home_score;
            $as = (int) $m->away_score;

            $rows[$hid]['played']++;
            $rows[$aid]['played']++;
            $rows[$hid]['goals_for'] += $hs;
            $rows[$hid]['goals_against'] += $as;
            $rows[$aid]['goals_for'] += $as;
            $rows[$aid]['goals_against'] += $hs;

            if ($hs > $as) {
                $rows[$hid]['won']++;
                $rows[$aid]['lost']++;
            } elseif ($hs < $as) {
                $rows[$aid]['won']++;
                $rows[$hid]['lost']++;
            } else {
                $rows[$hid]['drawn']++;
                $rows[$aid]['drawn']++;
            }
        }

        // Derive PTS/GD invariants explicitly (DB CHECK will reject mismatches).
        foreach ($rows as &$row) {
            $row['goal_diff'] = $row['goals_for'] - $row['goals_against'];
            $row['points'] = 3 * $row['won'] + $row['drawn'];
        }
        unset($row);

        // Upsert: matches the team_id UNIQUE constraint.
        Standing::query()->upsert(
            array_values($rows),
            ['team_id'],
            ['played', 'won', 'drawn', 'lost', 'goals_for', 'goals_against', 'goal_diff', 'points', 'updated_at']
        );
    }
}
