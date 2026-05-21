<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * TeamSeeder (US-A-01, OQ-05 closed)
 *
 * Brief'in 4 köklü takımı + sabit power/supporter/keeper değerleri.
 * Power: Liverpool 88, Manchester City 90, Chelsea 82, Arsenal 80.
 * Supporter & Keeper: 60..80 aralığında (deterministik, fixed).
 */
class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $teams = [
            ['name' => 'Liverpool',        'power' => 88, 'supporter' => 78, 'keeper' => 80],
            ['name' => 'Manchester City',  'power' => 90, 'supporter' => 80, 'keeper' => 75],
            ['name' => 'Chelsea',          'power' => 82, 'supporter' => 70, 'keeper' => 72],
            ['name' => 'Arsenal',          'power' => 80, 'supporter' => 68, 'keeper' => 70],
        ];

        foreach ($teams as $row) {
            Team::query()->updateOrCreate(['name' => $row['name']], $row);
        }
    }
}
