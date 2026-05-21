<?php

namespace Tests\Feature\Api;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayAllWeeksTest extends TestCase
{
    use RefreshDatabase;

    public function test_play_all_weeks_completes_season(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $response = $this->postJson('/api/league/play-all-weeks');
        $response->assertOk();

        $settings = LeagueSettings::query()->find(1);
        $this->assertSame(6, $settings->current_week);
        $this->assertSame('finished', $settings->status);
        $this->assertSame(0, MatchModel::query()->whereNull('home_score')->count(), 'All matches must be played');
    }
}
