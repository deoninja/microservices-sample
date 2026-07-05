<?php

return [
    // Path where compiled Blade templates are cached.
    // Even though this app has no views, Laravel's ViewServiceProvider
    // requires this config key to exist or it crashes on boot.
    'compiled' => env('VIEW_COMPILED_PATH', realpath(storage_path('framework/views'))),

    // Directories Laravel scans for view files.
    // Empty array because this is an API-only app with no Blade templates.
    'paths' => [
        resource_path('views'),
    ],
];
