<?php

namespace App\Providers;

use App\Auth\IdentityProvider;
use App\Contracts\ServiceClientInterface;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;

/*
 * app/Providers/AppServiceProvider.php — Service Container Bindings
 *
 * This provider registers all the bindings that Laravel's service container
 * needs to resolve injected dependencies automatically.
 *
 * By binding interfaces to concrete implementations here, we follow the
 * Dependency Inversion Principle — controllers depend on abstractions,
 * and this provider decides which concrete implementation to use.
 */

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind microservice clients to the container as singletons.
        // This means the same instance is reused across all requests.
        $this->app->singleton(UserService::class, function ($app) {
            return new UserService();
        });

        $this->app->singleton(ProductService::class, function ($app) {
            return new ProductService();
        });

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService();
        });

        // Bind the IdentityProvider as a singleton.
        $this->app->singleton(IdentityProvider::class);

        // Override the exception handler so ALL errors return JSON.
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
