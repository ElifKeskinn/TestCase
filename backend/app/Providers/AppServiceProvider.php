<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the SeededRandomInterface to its default implementation.
        $this->app->bind(
            \App\Domain\Support\SeededRandomInterface::class,
            \App\Domain\Support\SeededRandom::class
        );
    }

    public function boot(): void
    {
        // Enable SQLite WAL + foreign keys + busy_timeout on every connection
        // (§4.5.10). Apply only to the sqlite connection.
        if (DB::connection()->getDriverName() === 'sqlite') {
            try {
                DB::statement('PRAGMA foreign_keys = ON;');
                DB::statement('PRAGMA journal_mode = WAL;');
                DB::statement('PRAGMA busy_timeout = 5000;');
                DB::statement('PRAGMA synchronous = NORMAL;');
            } catch (\Throwable $e) {
                // Ignore — tests may not have a DB at boot time.
            }
        }

        // Rate limiters (NFR-17, §4.5.8, US-H-09).
        RateLimiter::for('reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });
        RateLimiter::for('play_all', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
        RateLimiter::for('generate_fixtures', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
