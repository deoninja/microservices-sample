<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Override the exception handler so ALL errors return JSON, never HTML.
        // Without this, Laravel returns an HTML page for 404/405/500 errors,
        // which breaks API clients that expect JSON.
        $this->app->bind(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );
    }

    public function boot(): void
    {
        //
    }
}
