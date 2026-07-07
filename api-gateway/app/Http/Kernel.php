<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

/*
 * app/Http/Kernel.php — HTTP Middleware Stack
 *
 * The Kernel defines which middleware runs on which requests.
 * Think of middleware as a pipeline — every request passes through
 * these layers in order before reaching a controller, and the
 * response passes back through them in reverse on the way out.
 *
 * There are three levels:
 *   1. $middleware       — runs on EVERY single request, no exceptions
 *   2. $middlewareGroups — runs on requests that match a named group (e.g. 'api')
 *   3. $middlewareAliases — short names you can attach to individual routes
 */

class Kernel extends HttpKernel
{
    /*
     * Global Middleware — runs on every request to the application.
     *
     * These execute before routing even happens, so they apply to
     * all routes including health checks and public endpoints.
     */
    protected $middleware = [
        // Adds CORS headers (Access-Control-Allow-Origin etc.) to every response
        // so browsers allow the frontend at localhost:3000 to call this API.
        \Illuminate\Http\Middleware\HandleCors::class,

        // Rejects requests whose body exceeds the limit set in php.ini (post_max_size).
        // Prevents oversized payloads from crashing the app.
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,

        // Converts any empty string input values ("") to null.
        // Keeps data clean — e.g. an empty name field becomes null instead of "".
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /*
     * Middleware Groups — a named set of middleware applied to a group of routes.
     *
     * The 'api' group is applied to all routes in routes/api.php by
     * RouteServiceProvider. So every /api/* route runs these automatically.
     */
    protected $middlewareGroups = [
        'api' => [
            // CORS is included here too so it runs specifically for API routes.
            \Illuminate\Http\Middleware\HandleCors::class,

            // Same empty-string-to-null cleanup for API request bodies.
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ],
    ];

    /*
     * Middleware Aliases — short names you attach to specific routes or groups.
     *
     * Instead of writing the full class name in routes/api.php, you use the alias:
     *   Route::get('/orders')->middleware('jwt.auth')
     *
     * Laravel looks up 'jwt.auth' here and runs JwtMiddleware.
     */
    protected $middlewareAliases = [
        //
    ];
}
