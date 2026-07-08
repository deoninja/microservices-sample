<?php

return [
    'name' => env('APP_NAME', 'API Gateway'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => env('APP_CIPHER', 'AES-256-CBC'),
    'key' => env('APP_KEY', 'base64:DM7QNXETl7lWPjUdg6bPvHu4T1Ef3cDMKbwVlhlhaYM='),
    'namespace' => 'App',
    'providers' => [
        // Core framework providers needed by every Laravel app
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,

        // Needed for request validation (used in AuthController)
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,

        // Needed for Blade error pages and storage paths
        Illuminate\View\ViewServiceProvider::class,

        // Auth system — registers the 'auth' singleton in the container
        Illuminate\Auth\AuthServiceProvider::class,

        // OAuth2 server for API token authentication
        Laravel\Passport\PassportServiceProvider::class,

        // App-specific providers
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ],
];
