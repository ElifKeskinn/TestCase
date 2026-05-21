<?php

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\LeagueLockedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API is stateless (no CSRF, no session) per NFR-19.
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Laravel 11 does NOT auto-register HandleCors; we prepend it explicitly
        // so the config/cors.php allowlist (FRONTEND_URL + localhost:5173) applies.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Map domain exceptions to HTTP envelopes (§4.5.8).
        $exceptions->render(function (LeagueLockedException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'league_locked',
                    'message' => $e->getMessage(),
                ], 423);
            }
        });
        $exceptions->render(function (IdempotencyConflictException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'conflict',
                    'message' => $e->getMessage(),
                ], 409);
            }
        });
        // Force JSON envelope for API 404s (avoids HTML error page).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'not_found',
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Resource not found.',
                ], 404);
            }
        });
        // ValidationException already renders 422 JSON for API requests by default.
    })->create();
