<?php

namespace Tests\Feature\Api;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Standing;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_clears_matches_and_standings(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertOk();

        $this->assertGreaterThan(0, Standing::query()->count());
        $this->assertGreaterThan(0, MatchModel::query()->count());

        $response = $this->postJson('/api/league/reset');
        $response->assertOk();

        $this->assertSame(0, MatchModel::query()->count());
        $this->assertSame(0, Standing::query()->count());

        $settings = LeagueSettings::query()->find(1);
        $this->assertSame(0, $settings->current_week);
        $this->assertSame('idle', $settings->status);
    }
}
