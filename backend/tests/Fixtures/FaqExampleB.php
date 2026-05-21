<?php

namespace Tests\Fixtures;

use App\Models\LeagueSettings;
use App\Models\MatchModel;
use App\Models\Standing;
use Illuminate\Support\Carbon;

/**
 * Senaryo F-Test-2 (US-E-04, OQ-02; spec section 9.6 F-Test-2):
 *   Liverpool 12 PTS, Manchester City 12 PTS, Chelsea 7 PTS, Arsenal 4 PTS.
 *   Kalan 1 hafta (week 6): Liverpool (home) vs Manchester City (away).
 *
 * Beklenen (OQ-02): Liv ve MC her biri 35..65; toplam >= 95; digerleri her biri < 5.
 *
 * Tasarim notu (denge):
 *  - Goal-difference asimetrisi (Liv:+6, MC:+8) bilincli secilmistir. Eshit puanda
 *    (W6 berabere bitince) tiebreak chain (PTS desc -> GD desc) MC yi siralamaya
 *    ust koyar, boylece ev avantaji (config home_advantage=0.30) sadece
 *    Liverpool un outright galibiyetini sampiyonluga cevirir; beraberlik MC ye
 *    gider. Bu durum, [35..65] OQ-02 bandinin iki tarafini da matematiksel
 *    olarak saglar (yaklasik 53 / 47 dagilim).
 *
 *  - Power profilleri (OQ-05): Liverpool 88, Manchester City 90 -- sabittir.
 */
final class FaqExampleB
{
    /**
     * @param array<string, \App\Models\Team> $teams
     */
    public static function setup(array $teams): void
    {
        $now = Carbon::now();
        // Liv GD=+6 (lower); MC GD=+8 (higher). Tiebreak (PTS=GD desc) puts MC #1.
        $standings = [
            ['team_id' => $teams['Liverpool']->id,       'played' => 5, 'won' => 4, 'drawn' => 0, 'lost' => 1, 'goals_for' => 11, 'goals_against' => 5, 'goal_diff' => 6, 'points' => 12],
            ['team_id' => $teams['Manchester City']->id, 'played' => 5, 'won' => 4, 'drawn' => 0, 'lost' => 1, 'goals_for' => 12, 'goals_against' => 4, 'goal_diff' => 8, 'points' => 12],
            ['team_id' => $teams['Chelsea']->id,         'played' => 5, 'won' => 2, 'drawn' => 1, 'lost' => 2, 'goals_for' => 7, 'goals_against' => 7, 'goal_diff' => 0, 'points' => 7],
            ['team_id' => $teams['Arsenal']->id,         'played' => 5, 'won' => 1, 'drawn' => 1, 'lost' => 3, 'goals_for' => 4, 'goals_against' => 8, 'goal_diff' => -4, 'points' => 4],
        ];
        foreach ($standings as $row) {
            Standing::query()->create($row + ['updated_at' => $now]);
        }

        LeagueSettings::query()->updateOrCreate(
            ['id' => (int) config('league.settings_id', 1)],
            [
                'team_count' => 4,
                'current_week' => 5,
                'total_weeks' => 6,
                'seed' => 42,
                'status' => LeagueSettings::STATUS_IDLE,
                'status_updated_at' => $now,
            ]
        );

        // Yalnizca son hafta kalan maclar:
        $remaining = [
            ['week' => 6, 'home_team_id' => $teams['Liverpool']->id, 'away_team_id' => $teams['Manchester City']->id],
            ['week' => 6, 'home_team_id' => $teams['Chelsea']->id,   'away_team_id' => $teams['Arsenal']->id],
        ];
        foreach ($remaining as $row) {
            MatchModel::query()->create($row + [
                'home_score' => null, 'away_score' => null, 'played_at' => null, 'version' => 1, 'editions_count' => 0,
            ]);
        }
    }
}