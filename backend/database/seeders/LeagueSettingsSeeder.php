<?php

namespace Database\Seeders;

use App\Models\LeagueSettings;
use Illuminate\Database\Seeder;

/**
 * LeagueSettingsSeeder (singleton, id=1, US-A-01 + §4.2)
 */
class LeagueSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settingsId = (int) config('league.settings_id', 1);

        LeagueSettings::query()->updateOrCreate(
            ['id' => $settingsId],
            [
                'team_count' => 4,
                'current_week' => 0,
                'total_weeks' => 6,        // 2 * (4 - 1)
                'seed' => (int) config('league.default_seed', 42),
                'status' => 'idle',
                'status_updated_at' => now(),
            ]
        );
    }
}
