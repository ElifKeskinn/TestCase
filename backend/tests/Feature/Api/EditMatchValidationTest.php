<?php

namespace Tests\Feature\Api;

use App\Models\MatchModel;
use Database\Seeders\LeagueSettingsSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EditMatchValidationTest — covers US-G-03 AC-5 explicitly.
 */
final class EditMatchValidationTest extends TestCase
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

    public function test_rejects_negative_score(): void
    {
        $m = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$m->id}", [
            'home_score' => -1, 'away_score' => 0, 'expected_version' => $m->version,
        ])->assertStatus(422);
    }

    public function test_rejects_over_20(): void
    {
        $m = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$m->id}", [
            'home_score' => 25, 'away_score' => 1, 'expected_version' => $m->version,
        ])->assertStatus(422);
    }

    public function test_rejects_non_integer(): void
    {
        $m = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$m->id}", [
            'home_score' => 'abc', 'away_score' => 0, 'expected_version' => $m->version,
        ])->assertStatus(422);
    }

    public function test_requires_expected_version(): void
    {
        $m = $this->setupPlayedMatch();
        $this->patchJson("/api/matches/{$m->id}", [
            'home_score' => 1, 'away_score' => 0,
        ])->assertStatus(422);
    }
}
