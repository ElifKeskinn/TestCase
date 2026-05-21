<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS allowlist (NFR-19, OQ-04 closed)
    |--------------------------------------------------------------------------
    |
    | Backend ve frontend ayrı host'larda deploy edileceği için allowlist
    | zorunludur. FRONTEND_URL env değişkeni ile prod URL injekte edilir.
    | Yerel geliştirme için Vite default'u (localhost:5173) eklenmiştir.
    */
    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL') ? rtrim((string) env('FRONTEND_URL'), '/') : null,
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
