<?php

namespace App\Providers;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Clients\HttpOrderClient;
use App\Gateway\Clients\HttpProductClient;
use App\Gateway\Clients\HttpUserClient;
use App\Gateway\Contracts\OrderClientInterface;
use App\Gateway\Contracts\ProductClientInterface;
use App\Gateway\Contracts\UserClientInterface;
use App\Services\AuthService;
use Illuminate\Support\ServiceProvider;

/*
 * app/Providers/AppServiceProvider.php — Service Container Bindings
 *
 * Following Clean Architecture, this provider wires the Dependency Injection
 * container so that Presentation-layer controllers receive the correct
 * Infrastructure-layer adapters behind interface abstractions.
 *
 * Layer mapping:
 *   Interfaces (Ports)     →  app/Gateway/Contracts/
 *   Implementations (Adapters)  →  app/Gateway/Clients/
 *   Application Services   →  app/Services/
 *   Presentation           →  app/Http/Controllers/
 */

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ── Infrastructure Layer — Port-to-Adapter bindings ─────────────────
        // Each *ClientInterface (port) is bound to its Http*Client (adapter).
        // Singletons ensure the same Guzzle/client instance is reused.

        $this->app->singleton(UserClientInterface::class, function () {
            return new HttpUserClient();
        });

        $this->app->singleton(ProductClientInterface::class, function () {
            return new HttpProductClient();
        });

        $this->app->singleton(OrderClientInterface::class, function () {
            return new HttpOrderClient();
        });

        // ── Infrastructure — Identity Provider ─────────────────────────────
        $this->app->singleton(IdentityProvider::class);

        // ── Application Layer — Orchestration Services ─────────────────────
        // AuthService depends on UserClientInterface, which is resolved
        // automatically by the container through the binding above.
        $this->app->singleton(AuthService::class);
        $this->app->singleton(OrderAggregationService::class);

        // ── Error Handling ─────────────────────────────────────────────────
        // Ensure ALL errors return JSON (never HTML), even for auth failures.
        $this->app->bind(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
