<?php

use App\Http\Controllers\LeagueController;
use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — stateless, no CSRF (NFR-19)
|--------------------------------------------------------------------------
*/

Route::prefix('league')->group(function () {
    Route::get('/state', [LeagueController::class, 'state']);
    Route::get('/predictions', [LeagueController::class, 'predictions']);

    Route::post('/generate-fixtures', [LeagueController::class, 'generateFixtures'])
        ->middleware('throttle:generate_fixtures');

    Route::post('/play-next-week', [LeagueController::class, 'playNextWeek']);

    Route::post('/play-all-weeks', [LeagueController::class, 'playAllWeeks'])
        ->middleware('throttle:play_all');

    Route::post('/reset', [LeagueController::class, 'reset'])
        ->middleware('throttle:reset');
});

Route::patch('/matches/{id}', [MatchController::class, 'update'])
    ->whereNumber('id');
