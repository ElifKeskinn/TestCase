<?php

namespace Tests\Fixtures;

use App\Models\Team;

/**
 * Reusable team profiles (OQ-05 closed).
 */
final class TeamPowerProfiles
{
    /**
     * @return Team[]  Indexed by name.
     */
    public static function seed(): array
    {
        $rows = [
            ['name' => 'Liverpool',       'power' => 88, 'supporter' => 78, 'keeper' => 80],
            ['name' => 'Manchester City', 'power' => 90, 'supporter' => 80, 'keeper' => 75],
            ['name' => 'Chelsea',         'power' => 82, 'supporter' => 70, 'keeper' => 72],
            ['name' => 'Arsenal',         'power' => 80, 'supporter' => 68, 'keeper' => 70],
        ];
        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = Team::query()->create($row);
        }
        return $out;
    }
}
