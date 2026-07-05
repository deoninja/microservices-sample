<?php

/*
 * config/database.php — Minimal Database Configuration
 *
 * This API Gateway does not use a database — all data lives in the
 * Node.js microservices. However, Laravel's ConsoleSupportServiceProvider
 * (needed for Artisan commands) requires this file to exist.
 *
 * The 'default' connection is set to 'none' which is never actually used.
 */

return [
    'default'     => env('DB_CONNECTION', 'none'),
    'connections' => [],
    'migrations'  => 'migrations',
];
