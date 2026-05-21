<?php

namespace App\Domain\Services;

use App\Domain\Support\SeededRandom;
use App\Models\Team;

/**
 * MatchSimulator — probabilistic score generator (US-C-01..C-05).
 *
 * Model: Poisson sampling on per-side expected goals (λ_home, λ_away).
 *
 *   λ_home = base_lambda * (power_home / 50) * (1 + supporter_home/200) / (1 + keeper_away/200)
 *            * (1 + home_advantage)
 *   λ_away = base_lambda * (power_away / 50)                              / (1 + keeper_home/200)
 *
 * Where `power` ∈ [1..100], `supporter`/`keeper` ∈ [0..100] (nullable, defaulted to 50).
 *
 * - Deterministic given a seed (US-C-04) — uses SeededRandom (Poisson via Knuth).
 * - Upset guaranteed (US-C-05) — λ_away > 0 for any non-trivial team pair,
 *   so weak team has non-zero win probability.
 * - The score is clamped to [0..20] by the matches CHECK constraint; we cap here too.
 */
final class MatchSimulator
{
    private const BASE_LAMBDA = 1.35;
    private const SCORE_CAP = 20;

    public function simulate(Team $home, Team $away, int $seed): array
    {
        $rng = new SeededRandom($seed);

        $homeAdv = (float) config('league.home_advantage', 0.30);

        $homeSupporter = $home->supporter ?? 50;
        $awayKeeper = $away->keeper ?? 50;
        $homeKeeper = $home->keeper ?? 50;
        $awaySupporter = $away->supporter ?? 50;

        $lambdaHome = self::BASE_LAMBDA
            * ($home->power / 50.0)
            * (1.0 + $homeSupporter / 200.0)
            / (1.0 + $awayKeeper / 200.0)
            * (1.0 + $homeAdv);

        $lambdaAway = self::BASE_LAMBDA
            * ($away->power / 50.0)
            * (1.0 + $awaySupporter / 200.0)
            / (1.0 + $homeKeeper / 200.0);

        $homeScore = min(self::SCORE_CAP, $rng->poisson($lambdaHome));
        $awayScore = min(self::SCORE_CAP, $rng->poisson($lambdaAway));

        return [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ];
    }
}
