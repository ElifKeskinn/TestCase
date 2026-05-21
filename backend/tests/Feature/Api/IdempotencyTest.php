<?php

namespace Tests\Feature\Api;

use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Idempotency primitives (§4.5.5, US-H-06).
 */
final class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_play_next_week_at_season_end_returns_409(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $this->postJson('/api/league/play-all-weeks')->assertOk();

        $this->postJson('/api/league/play-next-week', ['expected_week' => 7])
            ->assertStatus(409);
    }

    public function test_repeated_play_next_with_same_expected_week_second_returns_409(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertOk();
        // Server has advanced to week=1, expects expected_week=2 next.
        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertStatus(409);
    }

    public function test_reset_idempotent_on_clean_state(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/reset')->assertOk();
        // A second reset on already-clean state must still return 200.
        $this->postJson('/api/league/reset')->assertOk();
    }
}
