<?php

namespace App\Domain\Tiebreak;

/**
 * StandingsSorter — fixed tiebreak chain (OQ-01 closed):
 *
 *     PTS desc → GD desc → GF desc → team_name asc (alphabetical, deterministic).
 *
 * Used both by ChampionshipPredictor (in-memory ranking) and as the read-side
 * mirror of the DB index `idx_standings_sort`.
 */
final class StandingsSorter
{
    /**
     * Sort an array of standing rows in-place.
     *
     * Each row must contain keys: points, goal_diff, goals_for, name.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function sort(array $rows): array
    {
        usort($rows, [self::class, 'compare']);
        return $rows;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function compare(array $a, array $b): int
    {
        if ($a['points'] !== $b['points']) {
            return $b['points'] <=> $a['points'];           // PTS desc
        }
        if ($a['goal_diff'] !== $b['goal_diff']) {
            return $b['goal_diff'] <=> $a['goal_diff'];     // GD desc
        }
        if ($a['goals_for'] !== $b['goals_for']) {
            return $b['goals_for'] <=> $a['goals_for'];     // GF desc
        }
        return strcmp((string) $a['name'], (string) $b['name']); // name asc
    }
}
