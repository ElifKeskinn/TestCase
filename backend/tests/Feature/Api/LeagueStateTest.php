<?php

namespace Tests\Feature\Api;

use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeagueStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_state_returns_seeded_teams(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        $response = $this->getJson('/api/league/state');
        $response->assertOk();
        // LeagueState envelope — mirrors frontend/src/types/league.ts.
        $response->assertJsonStructure([
            'settings' => ['team_count', 'current_week', 'total_weeks', 'status', 'seed'],
            'teams',
            'matches',
            'standings',
            'predictions',
        ]);
        $this->assertSame(4, count($response->json('teams')));
        $this->assertSame('idle', $response->json('settings.status'));
    }
}
