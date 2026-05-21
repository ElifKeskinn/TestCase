<?php

namespace Tests\Unit\Domain;

use App\Domain\Tiebreak\StandingsSorter;
use PHPUnit\Framework\TestCase;

/**
 * StandingsSorter — covers tiebreak chain (US-D-03, OQ-01).
 *
 *   PTS desc → GD desc → GF desc → name asc.
 */
final class StandingsSorterTest extends TestCase
{
    private function row(string $name, int $pts, int $gd, int $gf): array
    {
        return [
            'name' => $name, 'points' => $pts,
            'goal_diff' => $gd, 'goals_for' => $gf,
        ];
    }

    public function test_orders_by_points_desc(): void
    {
        $rows = [
            $this->row('A', 5, 0, 0),
            $this->row('B', 9, 0, 0),
            $this->row('C', 7, 0, 0),
        ];
        $sorted = StandingsSorter::sort($rows);
        $this->assertSame(['B','C','A'], array_column($sorted, 'name'));
    }

    public function test_pts_tie_breaks_on_gd(): void
    {
        $rows = [
            $this->row('A', 9, 1, 0),
            $this->row('B', 9, 5, 0),
            $this->row('C', 9, 3, 0),
        ];
        $sorted = StandingsSorter::sort($rows);
        $this->assertSame(['B','C','A'], array_column($sorted, 'name'));
    }

    public function test_pts_gd_tie_breaks_on_gf(): void
    {
        $rows = [
            $this->row('A', 9, 5, 10),
            $this->row('B', 9, 5, 20),
            $this->row('C', 9, 5, 15),
        ];
        $sorted = StandingsSorter::sort($rows);
        $this->assertSame(['B','C','A'], array_column($sorted, 'name'));
    }

    public function test_full_tie_breaks_alphabetically(): void
    {
        $rows = [
            $this->row('Arsenal', 0, 0, 0),
            $this->row('Liverpool', 0, 0, 0),
            $this->row('Chelsea', 0, 0, 0),
            $this->row('Manchester City', 0, 0, 0),
        ];
        $sorted = StandingsSorter::sort($rows);
        $this->assertSame(['Arsenal','Chelsea','Liverpool','Manchester City'], array_column($sorted, 'name'));
    }
}
