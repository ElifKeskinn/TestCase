<?php

namespace Tests\Feature\Api;

use App\Models\MatchModel;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EditMatchTest extends TestCase
{
    use RefreshDatabase;

    private function setupPlayedMatch(): MatchModel
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $this->postJson('/api/league/play-next-week', ['expected_week' => 1])->assertOk();
        return MatchModel::query()->whereNotNull('home_score')->first();
    }

    public function test_edit_match_updates_score_and_bumps_version(): void
    {
        $match = $this->setupPlayedMatch();
        $originalVersion = $match->version;

        $response = $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 4,
            'away_score' => 2,
            'expected_version' => $originalVersion,
        ]);
        $response->assertOk();

        // PATCH returns the full LeagueState envelope; locate the edited
        // match inside the `matches` array and verify the recorded fields.
        $matches = collect($response->json('matches'));
        $edited = $matches->firstWhere('id', $match->id);
        $this->assertNotNull($edited, 'Edited match must appear in the LeagueState envelope.');
        $this->assertSame(4, $edited['home_score']);
        $this->assertSame(2, $edited['away_score']);
        $this->assertSame($originalVersion + 1, $edited['version']);
        $this->assertSame(1, $edited['editions_count']);
    }

    public function test_edit_match_returns_409_on_version_mismatch(): void
    {
        $match = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 4, 'away_score' => 2, 'expected_version' => $match->version,
        ])->assertOk();

        // Stale expected_version (old value)
        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 5, 'away_score' => 3, 'expected_version' => $match->version,
        ])->assertStatus(409);
    }

    public function test_edit_match_returns_404_for_unknown_id(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);

        $this->patchJson('/api/matches/99999', [
            'home_score' => 1, 'away_score' => 0, 'expected_version' => 1,
        ])->assertStatus(404);
    }

    public function test_edit_match_returns_422_for_invalid_score(): void
    {
        $match = $this->setupPlayedMatch();

        // Negative
        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => -1, 'away_score' => 0, 'expected_version' => $match->version,
        ])->assertStatus(422);

        // Out of range
        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 21, 'away_score' => 0, 'expected_version' => $match->version,
        ])->assertStatus(422);
    }

    public function test_edit_match_returns_422_for_unplayed_match(): void
    {
        $this->seed(TeamSeeder::class);
        $this->seed(LeagueSettingsSeeder::class);
        $this->postJson('/api/league/generate-fixtures')->assertOk();
        $match = MatchModel::query()->whereNull('home_score')->first();

        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 1, 'away_score' => 0, 'expected_version' => $match->version,
        ])->assertStatus(422);
    }

    public function test_edit_match_requires_expected_version(): void
    {
        $match = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$match->id}", [
            'home_score' => 1, 'away_score' => 0,
        ])->assertStatus(422);
    }
}
