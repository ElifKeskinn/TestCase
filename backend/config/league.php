<?php

return [
    /*
    |--------------------------------------------------------------------------
    | League domain configuration
    |--------------------------------------------------------------------------
    |
    | Tüm domain-level "magic number"lar buradan okunur. Hiçbir yerde takım
    | sayısı veya hafta sayısı hard-code EDILMEZ (US-B-03, NFR-10).
    */

    // Default seed for reproducible simulations (overridable per-league_setting).
    'default_seed' => (int) env('SIMULATION_DEFAULT_SEED', 42),

    // Monte Carlo iteration count (NFR-02).
    'monte_carlo_runs' => (int) env('PREDICTION_MONTE_CARLO_RUNS', 10000),

    // Number of weeks before season end at which predictions become active.
    // Trigger formula (US-E-01): currentWeek > totalWeeks - prediction_window
    'prediction_window' => 3,

    // Score bounds (matches CHECK constraint mirrors).
    'min_score' => 0,
    'max_score' => 20,

    // Team count bounds (league_settings CHECK constraint mirrors).
    'min_team_count' => 2,
    'max_team_count' => 8,

    // Home advantage multiplier used by MatchSimulator.
    'home_advantage' => 0.30,

    // League settings singleton id.
    'settings_id' => 1,
];
