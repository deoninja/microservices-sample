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
│   ├── Auth/
│   │   └── IdentityProvider.php          # builds X-User-Id / X-User-Role headers (SRP)
│   ├── Contracts/
│   │   └── ServiceClientInterface.php    # contract for all microservice clients (DIP)
│   ├── Exceptions/
│   │   └── Handler.php                   # converts exceptions to JSON responses
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php        # handles login & register
│   │   │   ├── UserController.php        # user CRUD proxy (SRP)
│   │   │   ├── ProductController.php     # product CRUD proxy (SRP)
│   │   │   └── OrderController.php       # order CRUD proxy (SRP)
│   │   └── Kernel.php                    # registers middleware aliases
│   ├── Models/
│   │   └── User.php                      # Passport-authenticatable user model
│   ├── Providers/
│   │   ├── AppServiceProvider.php        # registers service bindings (DIP)
│   │   ├── AuthServiceProvider.php
│   │   └── RouteServiceProvider.php      # loads routes/api.php
│   └── Services/
│       ├── BaseService.php               # abstract HTTP client (base for all services)
│       ├── UserService.php               # User Service proxy (SRP)
│       ├── ProductService.php            # Product Service proxy (SRP)
│       └── OrderService.php              # Order Service proxy (SRP)
├── bootstrap/
│   └── app.php                           # boots the Laravel app
├── config/
│   ├── app.php                           # app config + service providers
│   ├── cors.php                          # CORS allowed origins
│   └── services.php                      # microservice URLs
├── routes/
│   └── api.php                           # all route definitions
└── ...

# At the project root:
docker-compose.yml                       # Docker env vars including APP_KEY
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

**Why this matters:** By pointing to `App\Http\Kernel`, Laravel uses our custom kernel which registers middleware aliases. Without this, protected routes would crash with an unknown middleware error.

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
    'auth' => \Illuminate\Auth\Middleware\Authenticate::class,  // ← used on protected routes
];
```

Think of middleware as a pipeline — every request passes through these layers before reaching a controller.

The `auth` alias is the standard Laravel authentication middleware. When used as `auth:api`, it resolves the `api` guard from `config/auth.php` (which uses Passport) and authenticates the user via the Bearer token.

---

## Step 3 — Service Providers (`config/app.php`)

This file lists all the service providers that boot during application startup. The following key providers are registered:

### `Illuminate\Auth\AuthServiceProvider`

This framework provider registers the `auth` singleton in the Laravel container:

```php
$this->app->singleton('auth', fn ($app) => new AuthManager($app));
```

Without this provider, any call to `Auth::user()`, `$request->user()`, or the `auth:api` middleware would fail with:
```
"Target class [auth] does not exist."
```

It also registers request rebind handlers so that `$request->user()` works correctly:

```php
$this->app->rebinding('request', function ($app, $request) {
    $request->setUserResolver(function ($guard = null) use ($app) {
        return call_user_func($app['auth']->userResolver(), $guard);
    });
});
```

### `Laravel\Passport\PassportServiceProvider`

Registered below the AuthServiceProvider, this provides OAuth2 token authentication. The `api` guard in `config/auth.php` uses the `passport` driver.

### Application Service Provider (`App\Providers\AppServiceProvider`)

This provider registers the microservice proxy clients and identity provider as singletons in the container, following the Dependency Inversion Principle:

```php
public function register(): void
{
    // Each microservice client is a singleton — same instance reused across requests
    $this->app->singleton(UserService::class);
    $this->app->singleton(ProductService::class);
    $this->app->singleton(OrderService::class);
    $this->app->singleton(IdentityProvider::class);

    // Override the exception handler for JSON-only responses
    $this->app->bind(
        \Illuminate\Contracts\Debug\ExceptionHandler::class,
        \App\Exceptions\Handler::class
    );
}
```

Because these services are bound here, controllers can declare them in their constructors and Laravel's container automatically resolves (injects) them.

---

## Step 4 — Configuration (`config/services.php`)

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

### Protected routes (require JWT token via Passport)

```php
// Users — all protected with middleware('auth:api')
GET    /api/users
GET    /api/users/{id}
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}

// Products — read is public, write is protected
GET    /api/products          ← no token needed
GET    /api/products/{id}     ← no token needed
POST   /api/products          ← auth:api (token required)
PUT    /api/products/{id}     ← auth:api (token required)
DELETE /api/products/{id}     ← auth:api (token required)

// Orders — all protected with middleware('auth:api')
GET  /api/orders
GET  /api/orders/{id}
POST /api/orders
PUT  /api/orders/{id}/status
```

The `->middleware('auth:api')` call uses Laravel's standard `auth` middleware (registered in Kernel.php) with the `api` guard from `config/auth.php`. This guard uses Passport to validate the Bearer token and resolve the authenticated user.

### How Passport auth works

```
Incoming request with Authorization: Bearer <token>
      │
      ▼
1. auth middleware resolves the 'api' guard from config/auth.php
      │
      ▼
2. Passport guard extracts the Bearer token from the header
      │
      ▼
3. Validates the token against the oauth_access_tokens table
      │
      ├── Invalid/expired → throws AuthenticationException → 401 response
      │
      └── Valid → resolves the User model from oauth_clients and the token's user_id
                    │
                    ▼
4. Authenticated user is available via:
   - $request->user()      ← returns the User model
   - Auth::user()           ← same user model
   - Auth::id()             ← user's ID
```

**Token payload (Passport personal access token):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "user": { "id": 1, "username": "admin", "name": "Admin User", "email": "admin@example.com", "role": "admin" }
}
```

---

## Step 6 — Services Layer (`app/Services/`)

Following the **Single Responsibility Principle**, each microservice has its own dedicated service class. Controllers delegate all HTTP communication to these services.

### Architecture

```
Controller (request handling)          ← SRP: only receives requests, returns responses
    │
    │  depends on abstraction (DIP)
    ▼
ServiceClientInterface                 ← contract defines get/post/put/delete
    │
    │  implemented by
    ▼
BaseService (abstract)                 ← shared HTTP logic (Guzzle, error handling)
    │
    ├── UserService    → http://user-service:3001
    ├── ProductService → http://product-service:3002
    └── OrderService   → http://order-service:3003
```

### `ServiceClientInterface` (`app/Contracts/ServiceClientInterface.php`)

This is the contract that all microservice clients must implement. By depending on this abstraction, controllers are decoupled from the concrete HTTP implementation (Dependency Inversion Principle).

```php
interface ServiceClientInterface
{
    public function getBaseUrl(): string;
    public function get(string $path, array $headers = []): array;
    public function post(string $path, array $data = [], array $headers = []): array;
    public function put(string $path, array $data = [], array $headers = []): array;
    public function delete(string $path, array $headers = []): array;
}
```

### `BaseService` (`app/Services/BaseService.php`)

The abstract base class that implements the common HTTP logic:
- Creates a Guzzle client with sensible defaults (10s timeout, 5s connect timeout)
- Handles JSON encoding of request bodies
- Decodes JSON responses
- Catches `GuzzleException` and returns a 502 "Microservice unavailable" fallback
- All methods return a consistent `{status, body, success}` array

### Concrete Service Classes

Each service extends `BaseService` and only needs to provide its base URL and service-specific methods:

| Service | Base URL | Key methods |
|---------|----------|-------------|
| `UserService` | `config('services.user_service.url')` | `login()`, `register()`, `getAll()`, `getById()`, `create()`, `update()`, `remove()` |
| `ProductService` | `config('services.product_service.url')` | `getAll(search)`, `getById()`, `create()`, `update()`, `remove()` |
| `OrderService` | `config('services.order_service.url')` | `getAll()`, `getById()`, `create()`, `updateStatus()` |

### `IdentityProvider` (`app/Auth/IdentityProvider.php`)

A dedicated class with the **single responsibility** of building the identity headers (`X-User-Id`, `X-User-Role`) that get forwarded to microservices:

```php
class IdentityProvider
{
    public function getHeaders(Request $request): array
    {
        $headers = [];
        $user = $request->user() ?? Auth::user();
        if ($user) {
            $headers['X-User-Id']   = (string) $user->id;
            $headers['X-User-Role'] = $user->role ?? 'user';
        }
        return $headers;
    }
}
```

---

## Step 7 — Authentication Controller (`app/Http/Controllers/AuthController.php`)

Uses **Dependency Injection**: the `UserService` is injected via the constructor (not static calls).

```php
class AuthController extends Controller
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
}
```

### Login flow

```
POST /api/auth/login
{ "username": "admin", "password": "password" }
      │
      ▼
1. Validate input (username + password required)
      │
      ▼
2. Forward credentials via UserService->login()
   → POST http://user-service:3001/api/users/login
      │
      ├── Error → respond 401 "Invalid credentials"
      │
      └── Success → create/update local User record, issue Passport token
            │
            ▼
3. Return { token, user } to the browser
```

**Successful response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
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

## Step 8 — Per-Service Controllers (SRP + ISP)

Instead of one monolithic `GatewayController` handling every microservice, we now have **one controller per service**, following the **Single Responsibility Principle** and **Interface Segregation Principle**:

| Controller | Handles routes for | Injected dependencies |
|------------|-------------------|----------------------|
| `UserController` | `/api/users/*` | `UserService`, `IdentityProvider` |
| `ProductController` | `/api/products/*` | `ProductService`, `IdentityProvider` |
| `OrderController` | `/api/orders/*` | `OrderService`, `IdentityProvider` |

### Example — UserController

```php
class UserController extends Controller
{
    private UserService $userService;
    private IdentityProvider $identityProvider;

    public function __construct(UserService $userService, IdentityProvider $identityProvider)
    {
        // Dependencies injected by the container (DIP)
        $this->userService = $userService;
        $this->identityProvider = $identityProvider;
    }

    public function index(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->getAll($headers);
        return response()->json($result['body'], $result['status']);
    }
    // ... show, store, update, destroy follow the same pattern
}
```

### How headers are passed downstream

The `IdentityProvider` builds the identity headers, and the service classes forward them to the microservice via HTTP requests.

### Example — GET /api/orders

```
GET /api/orders
Authorization: Bearer <token>
      │
      ▼
auth:api middleware (Passport) validates the token
      → resolves the User model
      │
      ▼
OrderController::index()
      │
      ▼
IdentityProvider::getHeaders($request)
  → reads $request->user() → X-User-Id: 2, X-User-Role: user
      │
      ▼
OrderService::getAll($headers)
  → GET http://order-service:3003/api/orders
  → with X-User-Id and X-User-Role headers
      │
      ▼
Order Service responds with orders for user 2
      │
      ▼
OrderController returns the same response to the browser
```

---

## Step 9 — Base Service (`app/Services/BaseService.php`)

This is the abstract class that makes the actual HTTP call to a microservice via Guzzle. All concrete services (`UserService`, `ProductService`, `OrderService`) extend it.

Every request returns a consistent array:

| Key       | Type    | Description                              |
|-----------|---------|------------------------------------------|
| `status`  | int     | HTTP status code from the microservice   |
| `body`    | array   | Decoded JSON response body               |
| `success` | bool    | `true` if status < 400                   |

**What happens if a microservice is down?**

Guzzle throws a `GuzzleException`. `BaseService::request()` catches it, logs the error, and returns:

```json
{
  "status": 502,
  "body": { "error": "Microservice unavailable", "message": "..." },
  "success": false
}
```

The gateway then sends a `502 Bad Gateway` response to the browser instead of crashing.

**Guzzle client settings (set in BaseService constructor):**

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
  │     → route has auth:api middleware         │
  │                                             │
  │  3. auth:api middleware (Passport)          │
  │     → reads Bearer token                   │
  │     → validates against oauth_access_tokens │
  │     → resolves User model (id:2)           │
  │     → sets Auth::user() on the container   │
  │                                             │
  │  4. OrderController::index()                │
  │     → IdentityProvider::getHeaders()        │
  │       → $request->user() returns user id=2 │
  │         X-User-Id: 2, X-User-Role: user    │
  │                                             │
  │  5. OrderService::getAll($headers)          │
  │     → BaseService.request('GET', ...)       │
  │     → GET http://order-service:3003/orders  │
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

## Exception Handler (`app/Exceptions/Handler.php`)

The exception handler catches exceptions thrown during request processing and converts them to consistent JSON responses.

### HTTP status codes returned

| Status | Exception | Response |
|--------|-----------|----------|
| `401` | `AuthenticationException` | `{"error":"Unauthenticated","message":"..."}` |
| `404` | `NotFoundHttpException` | `{"error":"Not found","message":"..."}` |
| `405` | `MethodNotAllowedHttpException` | `{"error":"Method not allowed","message":"..."}` |
| `422` | `ValidationException` | `{"error":"Validation failed","errors":{...}}` |
| `500` | Any other exception | `{"error":"Server error","message":"..."}` |

**Important:** The `AuthenticationException` handler returns `401` (not `500`) when the `auth:api` middleware rejects an invalid or missing token. This is the correct HTTP status for unauthenticated requests.

---

## Environment Variables Reference

All variables live in `api-gateway/.env`. Docker Compose overrides them via the `environment:` block in `docker-compose.yml`.

### Must be set

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_KEY` | `base64:...` | Laravel encryption key (must be 32 bytes when decoded) |

### Application config

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Environment name |
| `APP_DEBUG` | `true` | Show detailed errors |
| `APP_URL` | `http://localhost:8000` | Gateway base URL |

### Microservice URLs

| Variable | Default | Docker override |
|----------|---------|----------------|
| `USER_SERVICE_URL` | `http://localhost:3001` | `http://user-service:3001` |
| `PRODUCT_SERVICE_URL` | `http://localhost:3002` | `http://product-service:3002` |
| `ORDER_SERVICE_URL` | `http://localhost:3003` | `http://order-service:3003` |

### Frontend / Security

| Variable | Default | Description |
|----------|---------|-------------|
| `FRONTEND_URL` | `http://localhost:3000` | Allowed CORS origin |
| `JWT_SECRET` | `microservices-secret-key-2024` | Legacy JWT secret (used by user service for token validation) |

---

## Common Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `401 Unauthenticated` | No `Authorization` header sent | Add `Authorization: Bearer <token>` header |
| `400/401` from auth endpoints | Token is wrong or expired | Log in again to get a fresh token |
| `502 Microservice unavailable` | A Node.js service is not running | Start the relevant service (`docker compose up`) |
| `Target class [auth] does not exist` | `Illuminate\Auth\AuthServiceProvider` not registered | Add it to `config/app.php` providers list |
| `Unsupported cipher or incorrect key` | APP_KEY is invalid or wrong length | Generate a valid key with `php artisan key:generate` |
| CORS error in browser | Frontend URL not in allowed origins | Set `FRONTEND_URL` in `.env` to match your frontend URL |
