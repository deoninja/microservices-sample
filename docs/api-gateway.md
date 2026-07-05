# API Gateway — Walkthrough

This document explains how the Laravel API Gateway works — what it does, how each piece fits together, and what happens step by step when a request comes in.

---

## What is an API Gateway?

Instead of the frontend talking directly to three separate services (users, products, orders), it talks to **one single entry point** — the API Gateway.

The gateway is responsible for:
- **Authentication** — checking who you are (JWT tokens)
- **Routing** — deciding which microservice should handle your request
- **Proxying** — forwarding the request and sending the response back

```
Browser
  │
  │  POST /api/auth/login
  ▼
API Gateway :8000          ← you only talk to this
  │
  ├──► User Service :3001
  ├──► Product Service :3002
  └──► Order Service :3003
```

---

## Folder Structure

```
api-gateway/
├── app/
│   ├── Helpers/
│   │   └── ProxyHelper.php       # sends HTTP requests to microservices
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php    # handles login & register
│   │   │   └── GatewayController.php # handles all proxied routes
│   │   ├── Middleware/
│   │   │   └── JwtMiddleware.php     # checks JWT token on protected routes
│   │   └── Kernel.php               # registers middleware aliases
│   └── Providers/
│       └── RouteServiceProvider.php  # loads routes/api.php
├── bootstrap/
│   └── app.php                   # boots the Laravel app
├── config/
│   ├── cors.php                  # CORS allowed origins
│   └── services.php              # microservice URLs + JWT config
├── routes/
│   └── api.php                   # all route definitions
└── .env                          # environment variables
```

---

## Step 1 — Booting the App (`bootstrap/app.php`)

This is the very first file Laravel loads. It creates the application instance and wires up the HTTP Kernel.

```php
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class          // ← our custom Kernel, not Laravel's default
);
```

**Why this matters:** By pointing to `App\Http\Kernel`, Laravel uses our custom kernel which registers the `jwt.auth` middleware alias. Without this, protected routes would crash with an unknown middleware error.

---

## Step 2 — Middleware Registration (`app/Http/Kernel.php`)

The Kernel defines three things:

```php
// Runs on EVERY request (global)
protected $middleware = [
    HandleCors::class,                    // adds CORS headers
    ValidatePostSize::class,              // rejects oversized payloads
    ConvertEmptyStringsToNull::class,     // cleans up empty inputs
];

// Runs on all /api/* routes
protected $middlewareGroups = [
    'api' => [
        HandleCors::class,
        ConvertEmptyStringsToNull::class,
    ],
];

// Named aliases you can attach to individual routes
protected $middlewareAliases = [
    'jwt.auth' => JwtMiddleware::class,   // ← used on protected routes
];
```

Think of middleware as a pipeline — every request passes through these layers before reaching a controller.

---

## Step 3 — Configuration (`config/services.php`)

This file stores the URLs of each microservice and the JWT secret. Values come from `.env` so they can be changed per environment (local vs Docker vs production).

```php
return [
    'user_service'    => ['url' => env('USER_SERVICE_URL',    'http://localhost:3001')],
    'product_service' => ['url' => env('PRODUCT_SERVICE_URL', 'http://localhost:3002')],
    'order_service'   => ['url' => env('ORDER_SERVICE_URL',   'http://localhost:3003')],
    'jwt' => [
        'secret' => env('JWT_SECRET', 'microservices-secret-key-2024'),
        'ttl'    => 86400,   // token expires after 24 hours
    ],
];
```

**Local `.env`:**
```
USER_SERVICE_URL=http://localhost:3001
PRODUCT_SERVICE_URL=http://localhost:3002
ORDER_SERVICE_URL=http://localhost:3003
JWT_SECRET=microservices-secret-key-2024
```

**Docker `docker-compose.yml` overrides these to use service names:**
```
USER_SERVICE_URL=http://user-service:3001
```

---

## Step 4 — CORS (`config/cors.php`)

CORS (Cross-Origin Resource Sharing) controls which origins (domains) are allowed to call the API from a browser.

```php
return [
    'paths'           => ['api/*'],                              // apply to all /api routes
    'allowed_methods' => ['*'],                                  // GET, POST, PUT, DELETE, etc.
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_headers' => ['*'],                                  // including Authorization
];
```

The frontend at `http://localhost:3000` is the only allowed origin. If you deploy the frontend to a different URL, update `FRONTEND_URL` in `.env`.

---

## Step 5 — Routes (`routes/api.php`)

All routes are prefixed with `/api` automatically by `RouteServiceProvider`. So `Route::get('/health')` becomes `GET /api/health`.

### Public routes (no token needed)

```php
// Health check
GET /api/health

// Auth
POST /api/auth/login
POST /api/auth/register
```

### Protected routes (require JWT token)

```php
// Users — all protected
GET    /api/users
GET    /api/users/{id}
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}

// Products — read is public, write is protected
GET    /api/products          ← no token needed
GET    /api/products/{id}     ← no token needed
POST   /api/products          ← token required
PUT    /api/products/{id}     ← token required
DELETE /api/products/{id}     ← token required

// Orders — all protected
GET  /api/orders
GET  /api/orders/{id}
POST /api/orders
PUT  /api/orders/{id}/status
```

The `->middleware('jwt.auth')` call on a route means the `JwtMiddleware` runs before the controller method.

---

## Step 6 — JWT Middleware (`app/Http/Middleware/JwtMiddleware.php`)

This middleware runs on every protected route. Here is exactly what it does:

```
Incoming request
      │
      ▼
Does it have an Authorization: Bearer <token> header?
      │
      ├── NO  → return 401 "Authentication required"
      │
      └── YES → decode the token using JWT_SECRET
                    │
                    ├── Invalid/expired → return 401 "Invalid or expired token"
                    │
                    └── Valid → attach user data to the request, pass through
```

After a valid token is decoded, two things happen:

1. The decoded user object is stored on the request attributes as `jwt_user` — controllers can read it later.
2. Two headers are added to the request: `X-User-Id` and `X-User-Role` — these get forwarded to microservices so they know who is making the request.

```php
$request->attributes->set('jwt_user', (array) $decoded);
$request->headers->set('X-User-Id',   $decoded->sub);
$request->headers->set('X-User-Role', $decoded->role ?? 'user');
```

**JWT token payload structure:**
```json
{
  "sub":      1,
  "username": "admin",
  "name":     "Admin User",
  "email":    "admin@example.com",
  "role":     "admin",
  "iat":      1700000000,
  "exp":      1700086400
}
```

---

## Step 7 — Authentication Controller (`app/Http/Controllers/AuthController.php`)

### Login flow

```
POST /api/auth/login
{ "username": "admin", "password": "password" }
      │
      ▼
1. Validate input (username + password required)
      │
      ▼
2. Forward credentials to User Service
   POST http://localhost:3001/api/users/login
      │
      ├── User Service returns error → respond 401 "Invalid credentials"
      │
      └── User Service returns user object
            │
            ▼
3. Build JWT payload with user data + expiry
      │
      ▼
4. Sign token with JWT_SECRET using HS256 algorithm
      │
      ▼
5. Return { token, user } to the browser
```

**Successful response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "username": "admin",
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"
  }
}
```

### Register flow

```
POST /api/auth/register
{ "username": "...", "password": "...", "name": "...", "email": "..." }
      │
      ▼
1. Validate input (username min 3 chars, password min 6 chars, valid email)
      │
      ▼
2. Forward to User Service
   POST http://localhost:3001/api/users/register
      │
      ├── User Service returns error (e.g. username taken) → pass error through
      │
      └── Success → return 201 with new user object
```

---

## Step 8 — Gateway Controller (`app/Http/Controllers/GatewayController.php`)

This controller handles every proxied route. It does not contain any business logic — it just figures out the right microservice URL and forwards the request.

### How headers are passed downstream

Before forwarding, the controller reads the JWT user data that the middleware attached to the request and builds headers for the microservice:

```php
private function getHeaders(Request $request): array
{
    $headers = [];
    if ($request->attributes->has('jwt_user')) {
        $user = $request->attributes->get('jwt_user');
        $headers['X-User-Id']   = $user['sub']  ?? '';
        $headers['X-User-Role'] = $user['role'] ?? 'user';
    }
    return $headers;
}
```

This means the microservices never need to decode a JWT themselves — they just read `X-User-Id` and `X-User-Role` from the incoming headers.

### Example — GET /api/orders

```
GET /api/orders
Authorization: Bearer <token>
      │
      ▼
JwtMiddleware decodes token → attaches user to request
      │
      ▼
GatewayController::getOrders()
      │
      ▼
Builds URL: http://localhost:3003/api/orders
Adds headers: X-User-Id: 2, X-User-Role: user
      │
      ▼
ProxyHelper::forward('GET', url, [], headers)
      │
      ▼
Order Service responds with orders for user 2
      │
      ▼
Gateway returns the same response to the browser
```

---

## Step 9 — Proxy Helper (`app/Helpers/ProxyHelper.php`)

This is the class that actually makes the HTTP call to a microservice using Guzzle.

```php
public static function forward(string $method, string $url, array $data, array $headers): array
```

It always returns an array with three keys:

| Key       | Type    | Description                              |
|-----------|---------|------------------------------------------|
| `status`  | int     | HTTP status code from the microservice   |
| `body`    | array   | Decoded JSON response body               |
| `success` | bool    | `true` if status < 400                   |

**What happens if a microservice is down?**

Guzzle throws a `GuzzleException`. The helper catches it, logs the error, and returns:

```json
{
  "status": 502,
  "body": { "error": "Microservice unavailable", "message": "..." },
  "success": false
}
```

The gateway then sends a `502 Bad Gateway` response to the browser instead of crashing.

**Guzzle client settings:**

| Setting           | Value | Meaning                                      |
|-------------------|-------|----------------------------------------------|
| `timeout`         | 10s   | Give up waiting for a response after 10s     |
| `connect_timeout` | 5s    | Give up trying to connect after 5s           |
| `http_errors`     | false | Don't throw exceptions on 4xx/5xx responses  |

---

## Full Request Lifecycle

Here is the complete journey of a request from browser to microservice and back:

```
Browser sends:
  GET /api/orders
  Authorization: Bearer eyJ...

  ┌─────────────────────────────────────────────┐
  │              API Gateway :8000              │
  │                                             │
  │  1. HandleCors middleware                   │
  │     → adds CORS headers to response         │
  │                                             │
  │  2. RouteServiceProvider                    │
  │     → matches GET /api/orders               │
  │     → route has jwt.auth middleware         │
  │                                             │
  │  3. JwtMiddleware                           │
  │     → reads Bearer token                   │
  │     → decodes with JWT_SECRET               │
  │     → attaches user {id:2, role:"user"}     │
  │       to request attributes                 │
  │                                             │
  │  4. GatewayController::getOrders()          │
  │     → reads jwt_user from attributes        │
  │     → builds headers:                       │
  │         X-User-Id: 2                        │
  │         X-User-Role: user                   │
  │                                             │
  │  5. ProxyHelper::forward()                  │
  │     → GET http://localhost:3003/api/orders  │
  │       with X-User-Id and X-User-Role        │
  └─────────────────────────────────────────────┘
              │
              ▼
  ┌─────────────────────────────┐
  │   Order Service :3003       │
  │                             │
  │  reads X-User-Id: 2         │
  │  returns orders for user 2  │
  └─────────────────────────────┘
              │
              ▼
  Gateway returns orders JSON to browser
```

---

## Environment Variables Reference

All variables live in `api-gateway/.env`. Docker Compose overrides them via the `environment:` block.

| Variable              | Default                          | Description                        |
|-----------------------|----------------------------------|------------------------------------|
| `APP_KEY`             | `base64:...`                     | Laravel encryption key             |
| `APP_ENV`             | `local`                          | Environment name                   |
| `APP_DEBUG`           | `true`                           | Show detailed errors                |
| `APP_URL`             | `http://localhost:8000`          | Gateway base URL                   |
| `USER_SERVICE_URL`    | `http://localhost:3001`          | User Service address               |
| `PRODUCT_SERVICE_URL` | `http://localhost:3002`          | Product Service address            |
| `ORDER_SERVICE_URL`   | `http://localhost:3003`          | Order Service address              |
| `FRONTEND_URL`        | `http://localhost:3000`          | Allowed CORS origin                |
| `JWT_SECRET`          | `microservices-secret-key-2024`  | Secret used to sign/verify tokens  |

---

## Common Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `401 Authentication required` | No `Authorization` header sent | Add `Authorization: Bearer <token>` header |
| `401 Invalid or expired token` | Token is wrong or older than 24h | Log in again to get a fresh token |
| `502 Microservice unavailable` | A Node.js service is not running | Start the relevant service |
| `500` on any route | Middleware not registered | Check `bootstrap/app.php` binds `App\Http\Kernel` |
| CORS error in browser | Frontend URL not in allowed origins | Set `FRONTEND_URL` in `.env` to match your frontend URL |
