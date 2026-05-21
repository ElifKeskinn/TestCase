<?php

namespace Tests\Feature\Api;

use App\Models\MatchModel;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GenerateFixturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_fixtures_creates_12_matches_for_4_teams(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        $response = $this->postJson('/api/league/generate-fixtures');
        $response->assertOk();
        $this->assertSame(12, MatchModel::query()->count());
        $this->assertSame(6, MatchModel::query()->max('week'));
    }

    public function test_generate_fixtures_replaces_old(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $firstIds = MatchModel::query()->pluck('id')->all();

        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $secondIds = MatchModel::query()->pluck('id')->all();
        $this->assertCount(12, $secondIds);
        $this->assertEmpty(array_intersect($firstIds, $secondIds), 'Old match IDs must be wiped');
    }
}
