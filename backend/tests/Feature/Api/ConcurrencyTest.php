<?php

namespace Tests\Feature\Api;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ConcurrencyTest (US-H-06).
 *
 * NOTE: PHPUnit + SQLite (in-memory) cannot execute true parallel HTTP requests,
 * so we simulate the race condition by issuing back-to-back requests that
 * deliberately reference stale state — the second request must hit 409.
 */
final class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_play_next_week_second_returns_409(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        // Both requests carry expected_week=1; first succeeds (advances to 1),
        // second sees current_week=1 and rejects (server expects 2).
        $a = $this->postJson('/api/league/play-next-week', ['expected_week' => 1]);
        $b = $this->postJson('/api/league/play-next-week', ['expected_week' => 1]);

        $a->assertOk();
        $b->assertStatus(409);
    }

    public function test_concurrent_patch_match_second_returns_409(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertOk();
        $match = MatchModel::query()->whereNotNull('home_score')->first();

        // Two clients both hold the same expected_version. First wins, second 409.
        $v = $match->version;
        $a = $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 5, 'away_score' => 0, 'expected_version' => $v,
        ]);
        $b = $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 1, 'away_score' => 1, 'expected_version' => $v,
        ]);

        $a->assertOk();
        $b->assertStatus(409);
    }

    public function test_state_machine_blocks_during_running(): void
    {
        // Manually push status='running' and verify any mutation returns 423.
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();

        $settings = LeagueSettings::query()->find(1);
        $settings->status = LeagueSettings::STATUS_RUNNING;
        $settings->save();

        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])
            ->assertStatus(423);

        $this->postJson('/api/league/reset')->assertStatus(423);
    }

    public function test_state_machine_blocks_during_resetting(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertOk();
        $match = MatchModel::query()->whereNotNull('home_score')->first();

        $settings = LeagueSettings::query()->find(1);
        $settings->status = LeagueSettings::STATUS_RESETTING;
        $settings->save();

        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 1, 'away_score' => 0, 'expected_version' => $match->version,
        ])->assertStatus(423);
    }
}
