<?php

namespace Tests\Feature\Api;

use App\Models\LeagueSettings;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayNextWeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_play_next_week_advances_current_week(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $response = $this->postJson('/api/league/play-next-week', ['expected_week' => 1]);
        $response->assertOk();
        // LeagueState envelope is returned; current_week lives under `settings`.
        $response->assertJsonPath('settings.current_week', 1);
        $this->assertSame(1, LeagueSettings::query()->find(1)->current_week);
    }

    public function test_play_next_week_returns_409_on_mismatch(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $response = $this->postJson('/api/league/play-next-week', ['expected_week' => 5]);
        $response->assertStatus(409);
    }

    public function test_play_next_week_requires_expected_week(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $this->postJson('/api/league/play-next-week', [])->assertStatus(422);
    }
}
