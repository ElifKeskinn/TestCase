<?php

namespace Tests\Feature\Api;

use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * RateLimitTest (US-H-09, NFR-17):
 *   - reset: 3 / minute
 *   - play_all: 10 / minute
 *   - generate_fixtures: 10 / minute
 *
 * Hits each limiter directly via the named throttler.
 */
final class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('reset');
        RateLimiter::clear('play_all');
        RateLimiter::clear('generate_fixtures');
    }

    public function test_reset_throttle_returns_429(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/league/reset')->assertOk();
        }
        $this->postJson('/api/league/reset')->assertStatus(429);
    }

    public function test_play_all_throttle_returns_429(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        // 10 successful requests then 11th rejected (responses may be 200 or 423
        // depending on state; throttling kicks in regardless on the 11th).
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/league/play-all-weeks');
        }
        $this->postJson('/api/league/play-all-weeks')->assertStatus(429);
    }

    public function test_generate_fixtures_throttle_returns_429(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/league/generate-fixtures');
        }
        $this->postJson('/api/league/generate-fixtures')->assertStatus(429);
    }
}
