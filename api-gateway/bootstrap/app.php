<?php

/*
 * bootstrap/app.php — Application Boot File
 *
 * This is the very first file Laravel loads on every request.
 * Its job is to create the application instance and tell Laravel
 * which classes to use for the HTTP kernel, console kernel, and
 * exception handler.
 */

// Create the core Laravel application instance.
// dirname(__DIR__) resolves to the project root folder (one level up from bootstrap/).
// APP_BASE_PATH can override this via an environment variable if needed.
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// Bind our CUSTOM HTTP Kernel to the contract Laravel expects.
//
// IMPORTANT: We use App\Http\Kernel (not Laravel's default) because our
// custom Kernel registers the 'jwt.auth' middleware alias. If we left
// this as Illuminate\Foundation\Http\Kernel, the jwt.auth middleware
// would never be registered and all protected routes would crash.
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

// Bind the console kernel — handles Artisan CLI commands (php artisan ...).
// We use Laravel's default here since we have no custom Artisan commands.
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Illuminate\Foundation\Console\Kernel::class
);

// Bind the exception handler — controls how errors and exceptions are
// rendered (JSON responses in our case since this is an API-only app).
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Illuminate\Foundation\Exceptions\Handler::class
);

// Return the fully configured application instance.
// Laravel's public/index.php receives this and starts handling the request.
return $app;
