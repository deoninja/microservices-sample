<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * app/Providers/RouteServiceProvider.php — Route Loading & Rate Limiting
 *
 * This provider does two things:
 *   1. Defines a rate limit for the API (how many requests per minute are allowed).
 *   2. Loads routes/api.php and applies the 'api' middleware group + '/api' prefix
 *      to every route defined in that file.
 *
 * It runs once during application boot, before any request is handled.
 */

class RouteServiceProvider extends ServiceProvider
{
    // Default redirect path after login — not used in this API-only app
    // but required by the parent class.
    public const HOME = '/';

    public function boot(): void
    {
        // ── Rate Limiting ────────────────────────────────────────────────
        //
        // Define a rate limiter named 'api'. This limits how many requests
        // a single client can make per minute to prevent abuse.
        //
        // The limiter is keyed by user ID (if logged in) or IP address (if not).
        // This means authenticated users get their own 60 req/min bucket,
        // while anonymous users share a bucket per IP.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // ── Route Loading ────────────────────────────────────────────────
        //
        // Load routes/api.php and apply two things to every route in it:
        //
        //   ->middleware('api')  — runs the 'api' middleware group from Kernel.php
        //                          (CORS + ConvertEmptyStringsToNull)
        //
        //   ->prefix('api')      — prepends /api to every route path, so
        //                          Route::get('/health') becomes GET /api/health
        //                          Route::post('/auth/login') becomes POST /api/auth/login
        $this->routes(function () {
            Route::middleware([
                \Illuminate\Http\Middleware\HandleCors::class,
                \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            ])
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }
}
