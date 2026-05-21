<?php

namespace Tests\Fixtures;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Standing;
use Illuminate\Support\Carbon;

/**
 * Senaryo F-Test-1 (US-E-03, §9.6):
 *   Liverpool 18 PTS, MC 9, Chelsea 7, Arsenal 5.
 *   Kalan 2 hafta (week 5 + 6).
 *
 * Lider 9 puan önde, kalan haftalarda alabileceği max gain = 6, dolayısıyla
 * mathematically uncatchable → predictions[Liverpool] must be 100.00.
 */
final class FaqExampleA
{
    /**
     * @param array<string, \App\Models\Team> $teams  result of TeamPowerProfiles::seed()
     */
    public static function setup(array $teams): void
    {
        $now = Carbon::now();
        // Standings snapshot (4 maç oynanmış varsayımı).
        $standings = [
            // Liverpool: 6W 0D 0L = 18 pts; goals 18-2
            ['team_id' => $teams['Liverpool']->id,       'played' => 6, 'won' => 6, 'drawn' => 0, 'lost' => 0, 'goals_for' => 18, 'goals_against' => 2, 'goal_diff' => 16, 'points' => 18],
            // Manchester City: 3W 0D 3L = 9 pts; goals 8-6
            ['team_id' => $teams['Manchester City']->id, 'played' => 6, 'won' => 3, 'drawn' => 0, 'lost' => 3, 'goals_for' => 8, 'goals_against' => 6, 'goal_diff' => 2, 'points' => 9],
            // Chelsea: 2W 1D 3L = 7 pts; goals 6-7
            ['team_id' => $teams['Chelsea']->id,         'played' => 6, 'won' => 2, 'drawn' => 1, 'lost' => 3, 'goals_for' => 6, 'goals_against' => 7, 'goal_diff' => -1, 'points' => 7],
            // Arsenal: 1W 2D 3L = 5 pts; goals 5-8
            ['team_id' => $teams['Arsenal']->id,         'played' => 6, 'won' => 1, 'drawn' => 2, 'lost' => 3, 'goals_for' => 5, 'goals_against' => 8, 'goal_diff' => -3, 'points' => 5],
        ];
        foreach ($standings as $row) {
            Standing::query()->create($row + ['updated_at' => $now]);
        }

        // Settings: 4 takım, 8 hafta toplam (varsayım amaçlı — uncatchable testi hafta sayısından bağımsız).
        // total_weeks = 2*(4-1) = 6; current_week = 4; kalan = 2 hafta. Trigger: currentWeek(4) > 6-3=3 ✓.
        LeagueSettings::query()->updateOrCreate(
            ['id' => (int) config('league.settings_id', 1)],
            [
                'team_count' => 4,
                'current_week' => 4,
                'total_weeks' => 6,
                'seed' => 42,
                'status' => LeagueSettings::STATUS_IDLE,
                'status_updated_at' => $now,
            ]
        );

        // 2 maç kalmış: Week 5 ve Week 6'da Liverpool oynamasa bile uncatchable.
        // Burada non-leader takımların aralarında maç yapmasını sağlıyoruz.
        $remaining = [
            ['week' => 5, 'home_team_id' => $teams['Chelsea']->id,        'away_team_id' => $teams['Arsenal']->id],
            ['week' => 6, 'home_team_id' => $teams['Manchester City']->id, 'away_team_id' => $teams['Arsenal']->id],
        ];
        foreach ($remaining as $row) {
            MatchModel::query()->create($row + [
                'home_score' => null, 'away_score' => null, 'played_at' => null, 'version' => 1, 'editions_count' => 0,
            ]);
        }
    }
}
