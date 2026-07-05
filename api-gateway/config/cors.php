<?php

/*
 * config/cors.php — Cross-Origin Resource Sharing (CORS) Configuration
 *
 * CORS is a browser security rule. When JavaScript on one domain
 * (e.g. http://localhost:3000) tries to call an API on a different
 * domain (e.g. http://localhost:8000), the browser first checks
 * whether the API allows it by looking at the CORS headers in the response.
 *
 * This config is read by the HandleCors middleware registered in Kernel.php.
 */

return [

    // Which URL paths should have CORS headers applied.
    // 'api/*' means all routes under /api/ — which is everything in this gateway.
    'paths' => ['api/*'],

    // Which HTTP methods are allowed from the browser.
    // '*' means all methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, etc.
    'allowed_methods' => ['*'],

    // Which frontend origins (domains) are allowed to call this API.
    // Only the React frontend URL is allowed. Any other origin will be blocked by the browser.
    // Change FRONTEND_URL in .env if you deploy the frontend to a different domain.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    // Regex patterns for allowed origins — not needed here since we use an exact URL above.
    'allowed_origins_patterns' => [],

    // Which request headers the browser is allowed to send.
    // '*' allows everything, including 'Authorization' (needed for Bearer tokens)
    // and 'Content-Type' (needed for JSON request bodies).
    'allowed_headers' => ['*'],

    // Which response headers the browser JavaScript is allowed to read.
    // Empty means only the default safe headers are exposed.
    'exposed_headers' => [],

    // How long (in seconds) the browser can cache the CORS preflight response.
    // 0 means no caching — the browser checks on every request.
    'max_age' => 0,

    // Whether the browser should send cookies or HTTP auth credentials with requests.
    // false because we use JWT tokens in the Authorization header instead of cookies.
    'supports_credentials' => false,

];
