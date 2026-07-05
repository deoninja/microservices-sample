<?php

/*
 * config/services.php — External Service Configuration
 *
 * This file tells the gateway where each microservice lives and
 * stores the JWT signing secret.
 *
 * Values are read from the .env file using env().
 * The second argument to env() is the fallback default used when
 * the variable is not set — useful for local development without Docker.
 *
 * How to access these values anywhere in the app:
 *   config('services.user_service.url')   → http://localhost:3001
 *   config('services.jwt.secret')         → microservices-secret-key-2024
 */

return [

    // User Service — handles user accounts and authentication checks.
    // Local default: http://localhost:3001
    // Docker override (set in docker-compose.yml): http://user-service:3001
    'user_service' => [
        'url' => env('USER_SERVICE_URL', 'http://localhost:3001'),
    ],

    // Product Service — handles the product catalog (list, create, update, delete).
    // Local default: http://127.0.0.1:3002
    // Docker override: http://product-service:3002
    'product_service' => [
        'url' => env('PRODUCT_SERVICE_URL', 'http://localhost:3002'),
    ],

    // Order Service — handles order creation and status updates.
    // Local default: http://127.0.0.1:3003
    // Docker override: http://order-service:3003
    'order_service' => [
        'url' => env('ORDER_SERVICE_URL', 'http://localhost:3003'),
    ],

    // JWT (JSON Web Token) settings used by AuthController and JwtMiddleware.
    'jwt' => [
        // The secret key used to SIGN tokens on login and VERIFY them on requests.
        // Both signing and verifying must use the same secret — if they differ,
        // all tokens will be rejected as invalid.
        'secret' => env('JWT_SECRET', 'microservices-secret-key-2024'),

        // How long (in seconds) a token stays valid after it is issued.
        // 86400 seconds = 60 * 60 * 24 = 24 hours.
        // After this time the token expires and the user must log in again.
        'ttl' => 86400,
    ],

];
