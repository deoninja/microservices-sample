# API Gateway — Walkthrough

This document explains how the Laravel API Gateway works — what it does, how each piece fits together, and what happens step by step when a request comes in.

---

## What is an API Gateway?

Instead of the frontend talking directly to three separate services (users, products, orders), it talks to **one single entry point** — the API Gateway.

The gateway is responsible for:
- **Authentication** — checking who you are (Passport tokens)
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
├── Actions/                                 # SINGLE-RESPONSIBILITY ACTIONS
│   ├── Auth/
│   │   ├── LoginAction.php                    # authenticates user → returns token
│   │   └── RegisterAction.php                 # registers user → returns user data
│   ├── User/
│   │   ├── UserFetchAction.php                # GET /api/users
│   │   ├── UserShowAction.php                 # GET /api/users/{id}
│   │   ├── UserCreateAction.php               # POST /api/users
│   │   ├── UserUpdateAction.php               # PUT /api/users/{id}
│   │   └── UserDeleteAction.php               # DELETE /api/users/{id}
│   ├── Product/
│   │   ├── ProductFetchAction.php             # GET /api/products
│   │   ├── ProductShowAction.php              # GET /api/products/{id}
│   │   ├── ProductCreateAction.php            # POST /api/products
│   │   ├── ProductUpdateAction.php            # PUT /api/products/{id}
│   │   └── ProductDeleteAction.php            # DELETE /api/products/{id}
│   └── Order/
│       ├── OrderFetchAction.php               # GET /api/orders
│       ├── OrderShowAction.php                # GET /api/orders/{id}
│       ├── OrderCreateAction.php              # POST /api/orders
│       └── OrderUpdateStatusAction.php        # PUT /api/orders/{id}/status
├── Exceptions/
│   └── Handler.php                            # converts exceptions to JSON responses
├── Gateway/                                   # INFRASTRUCTURE LAYER (Adapters)
│   ├── Contracts/                             # Ports (Interfaces)
│   │   ├── UserClientInterface.php
│   │   ├── ProductClientInterface.php
│   │   └── OrderClientInterface.php
│   ├── Clients/                               # Concrete Adapters (Laravel Http facade)
│   │   ├── HttpUserClient.php
│   │   ├── HttpProductClient.php
│   │   ├── HttpOrderClient.php
│   │   └── IdentityProvider.php               # builds X-User-Id / X-User-Role headers
│   ├── Support/                               # Utilities
│   │   └── ResponseFormatter.php              # raw/except/only/map field filtering
│   └── DTOs/                                  # Optional typed schemas (JsonSerializable)
│       ├── UserData.php
│       ├── ProductData.php
│       └── OrderData.php
├── Http/                                      # PRESENTATION LAYER
│   ├── Controllers/
│   │   ├── Controller.php                     # base controller (no business logic)
│   │   ├── AuthController.php                 # delegates to LoginAction & RegisterAction
│   │   ├── UserController.php                 # delegates to User*Actions
│   │   ├── ProductController.php              # delegates to Product*Actions
│   │   └── OrderController.php                # delegates to Order*Actions
│   ├── Kernel.php                             # registers middleware aliases
│   └── Requests/                              # Input Validation (Form Requests)
│       ├── LoginRequest.php
│       └── RegisterRequest.php
├── Models/
│   └── User.php                               # Passport-authenticatable user model
├── Providers/
│   ├── AppServiceProvider.php                 # registers DI bindings
│   ├── AuthServiceProvider.php
│   └── RouteServiceProvider.php               # loads routes/api.php
└── Services/                                  # APPLICATION / ORCHESTRATION LAYER
    └── AuthService.php                        # orchestrates login/register flow
├── bootstrap/
│   └── app.php                                 # boots the Laravel app
├── config/
│   ├── app.php                                 # app config + service providers
│   ├── auth.php                                # guard configuration (Passport)
│   ├── cors.php                                # CORS allowed origins
│   └── services.php                            # microservice URLs
├── routes/
│   └── api.php                                 # all route definitions
└── ...

# At the project root:
docker-compose.yml                               # Docker env vars including APP_KEY
```

### Layer Architecture

```
┌──────────────────────────────────────────────────────┐
│               PRESENTATION LAYER                      │
│  Controllers (ultra-thin, just return $action())      │
│  Actions (single-responsibility invokable classes)     │
│  Form Requests (input validation)                     │
│  ───── depends on Application & Infrastructure ────── │
├──────────────────────────────────────────────────────┤
│               APPLICATION LAYER                        │
│  AuthService (orchestrates login/register flow)        │
│  ───── depends on Infrastructure (Ports/interfaces)    │
├──────────────────────────────────────────────────────┤
│              INFRASTRUCTURE LAYER                       │
│  Contracts (Ports — interfaces)                       │
│  Http*Clients (Adapters — raw JSON passthrough)       │
│  ResponseFormatter (optional field filtering)          │
│  DTOs (optional typed schemas)                         │
│  IdentityProvider (header builder)                     │
└──────────────────────────────────────────────────────┘
        │
        ▼
  External microservices (User, Product, Order)
```

### Clean Architecture Rules Enforced

| Rule | How it's applied |
|------|-----------------|
| **Dependencies point inward** | Presentation → Application → Infrastructure. Controllers never talk directly to HTTP. |
| **Controllers are ultra-thin** | Each method injects an **Action** as a parameter and calls `return $action(...)`. Zero logic. |
| **Actions do one thing** | Each action is an invokable class (`__invoke`) with a single responsibility — call a client, format a response. |
| **Raw response forwarding** | Http*Clients return raw decoded JSON arrays. No DTO mapping on every request. DTOs remain available for optional typed use. |
| **Per-action formatting** | Actions use `ResponseFormatter::except()/only()/map()` to strip, filter, or transform fields per-endpoint. `GET /api/products` strips `createdAt`; `GET /api/orders` renames fields and computes derived values. |
| **Application layer orchestrates** | `AuthService` coordinates the multi-step login/register flow across clients and models. |

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

This provider wires the DI container so controllers receive the correct infrastructure-layer adapters behind interface abstractions:

```php
public function register(): void
{
    // Infrastructure — Port-to-Adapter bindings
    $this->app->singleton(UserClientInterface::class, fn () => new HttpUserClient());
    $this->app->singleton(ProductClientInterface::class, fn () => new HttpProductClient());
    $this->app->singleton(OrderClientInterface::class, fn () => new HttpOrderClient());
    $this->app->singleton(IdentityProvider::class);

    // Application — orchestration services (auto-resolved)
    $this->app->singleton(AuthService::class);

    // Override the exception handler for JSON-only responses
    $this->app->bind(
        \Illuminate\Contracts\Debug\ExceptionHandler::class,
        \App\Exceptions\Handler::class
    );
}
```

---

## Step 4 — Configuration (`config/services.php`)

This file stores the URLs of each microservice.

```php
return [
    'user_service'    => ['url' => env('USER_SERVICE_URL',    'http://localhost:3001')],
    'product_service' => ['url' => env('PRODUCT_SERVICE_URL', 'http://localhost:3002')],
    'order_service'   => ['url' => env('ORDER_SERVICE_URL',   'http://localhost:3003')],
];
```

**Local `.env`:**
```
USER_SERVICE_URL=http://localhost:3001
PRODUCT_SERVICE_URL=http://localhost:3002
ORDER_SERVICE_URL=http://localhost:3003
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

The frontend at `http://localhost:3000` is the only allowed origin.

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

// Products (read-only)
GET /api/products
GET /api/products/{id}
```

### Protected routes (require JWT token via Passport)

```php
// Users — all protected with middleware('auth:api')
GET    /api/users
GET    /api/users/{id}
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}

// Products — write operations protected
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
      └── Valid → resolves the User model from the token's user_id
                    │
                    ▼
4. Authenticated user is available via:
   - $request->user()      ← returns the User model
   - Auth::user()           ← same user model
   - Auth::id()             ← user's ID
```

---

## Step 6 — Infrastructure Layer: DTOs (`app/Gateway/DTOs/`)

Data Transfer Objects provide typed, immutable representations of data coming from microservices. They ensure type safety at every layer boundary.

```php
class UserData
{
    public readonly int $id;
    public readonly string $username;
    public readonly string $name;
    public readonly string $email;
    public readonly string $role;

    public function __construct(...) { ... }

    // Named constructor — creates a DTO from a JSON response array
    public static function fromArray(array $data): self { ... }

    // Serialize back to array for JSON response
    public function toArray(): array { ... }
}
```

| DTO | Properties | Used by |
|-----|-----------|---------|
| `UserData` | id, username, name, email, role | AuthService, UserClient |
| `ProductData` | id, name, price, description, stock | ProductClient |
| `OrderData` | id, userId, customerName, items, total, status, createdAt | OrderClient |

---

## Step 7 — Infrastructure Layer: Raw Response Forwarding

Clients return the **raw decoded JSON** from microservices — no DTO mapping on every request. This is a proxy gateway, so pass-through is the default.

```
Microservice JSON → decode → raw array → [optional formatting in Action] → JSON → Frontend
```

If you need typed validation for specific data, DTOs (`UserData`, `ProductData`, `OrderData`) are still available via `UserData::fromArray($body)` — but they're no longer forced on every request.

---

## Step 8 — Infrastructure Layer: Contracts (`app/Gateway/Contracts/`)

Ports/interfaces that define the service contracts. All return `{status, body, success}` with `body` as a raw array.

```php
interface UserClientInterface
{
    /** @return array{status: int, body: array, success: bool} */
    public function login(string $username, string $password): array;
    public function register(...): array;
    public function getAll(array $headers = []): array;
    public function getById(int $id, array $headers = []): array;
    public function create(...): array;
    public function update(...): array;
    public function remove(int $id, array $headers = []): array;
}
```

---

## Step 9 — Infrastructure Layer: Adapters (`app/Gateway/Clients/`)

Concrete implementations using Laravel's `Http` facade. Simplified to raw passthrough:

```php
class HttpUserClient implements UserClientInterface
{
    public function login(string $username, string $password): array
    {
        $response = Http::timeout(10)
            ->post("{$this->baseUrl}/api/users/login", [
                'username' => $username,
                'password' => $password,
            ]);

        return [
            'status'  => $response->status(),
            'body'    => $response->json() ?? [],
            'success' => $response->successful(),
        ];
    }
}
```

| Client | Interface | Base URL |
|--------|-----------|----------|
| `HttpUserClient` | `UserClientInterface` | `config('services.user_service.url')` |
| `HttpProductClient` | `ProductClientInterface` | `config('services.product_service.url')` |
| `HttpOrderClient` | `OrderClientInterface` | `config('services.order_service.url')` |

### What happens when a microservice is down?

The `Http` facade throws a `ConnectionException`. The **Exception Handler** catches it and returns `502 Bad Gateway`.

### Identity Provider (`app/Gateway/Clients/IdentityProvider.php`)

Builds `X-User-Id` / `X-User-Role` headers from the authenticated user to forward to downstream services.

---

## Step 10 — Infrastructure Layer: ResponseFormatter (`app/Gateway/Support/ResponseFormatter.php`)

A lightweight utility for optional field filtering at the Action layer. Since clients now forward raw responses, Actions use `ResponseFormatter` when they need to strip or transform fields before sending to the frontend.

```php
ResponseFormatter::raw($body);                                   // passthrough
ResponseFormatter::except($body, ['createdAt', 'internal_note']); // remove fields
ResponseFormatter::only($body, ['id', 'name', 'price']);         // keep only these
ResponseFormatter::map($body, fn($item) => [                     // custom transform
    'id'    => $item['id'],
    'label' => strtoupper($item['name']),
]);
```

All methods transparently handle both **single items** (associative array) and **collections** (indexed array of items).

### Example: Strip `createdAt` from product listings

```php
class ProductFetchAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->productClient->getAll(...);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        $body = ResponseFormatter::except($result['body'], ['createdAt']);

        return response()->json($body, $result['status']);
    }
}
```

### Example: Transform order list with `map()`

```php
class OrderFetchAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->orderClient->getAll(...);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        $body = ResponseFormatter::map($result['body'], function (array $order): array {
            return [
                'id'        => $order['id'],
                'customer'  => $order['customerName'],   // rename field
                'status'    => $order['status'],
                'items'     => $order['items'],
                'itemCount' => count($order['items']),   // derived value
                'total'     => $order['total'],
                // userId and createdAt are intentionally excluded
            ];
        });

        return response()->json($body, $result['status']);
    }
}
```

Notice `GET /api/orders` transforms `customerName` → `customer`, adds `itemCount`, and omits `userId`/`createdAt`. Meanwhile `GET /api/orders/{id}` returns the raw data unchanged — demonstrating **per-action formatting control**.

---

## Step 11 — Application Layer: AuthService (`app/Services/AuthService.php`)

Coordinates login/register across UserClient + Passport. Works with raw arrays from the client:

```php
class AuthService
{
    public function login(string $username, string $password): array
    {
        $result = $this->userClient->login($username, $password);
        if (!$result['success']) {
            return ['success' => false, 'status' => 401, 'body' => ['error' => 'Invalid credentials']];
        }

        $userData = $result['body'];  // raw array from microservice

        $localUser = User::updateOrCreate(
            ['id' => $userData['id']],   // array access, not object
            $userData                    // already an array
        );

        $token = $localUser->createToken('api-access-token')->accessToken;

        return ['success' => true, 'token' => $token, 'user' => $userData];
    }
}
```

---

## Step 10 — Actions (`app/Actions/`)

Actions are **single-responsibility invokable classes** that sit between the controller and the Infrastructure/Application layers. Each action does exactly one thing — call a client, format a response.

This pattern makes controllers **ultra-thin**: each controller method just injects the action and calls `return $action(...)`.

### Pattern

```php
namespace App\Actions\User;

class UserFetchAction
{
    public function __construct(
        protected UserClientInterface $userClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->getAll($headers);
        return response()->json($result['body'], $result['status']);
    }
}
```

Key characteristics:
- **Invokable** — has a single `__invoke()` method (Laravel's container auto-resolves it)
- **Constructor injection** — receives dependencies via constructor (client interfaces, IdentityProvider)
- **Returns JsonResponse** — handles both success and error cases
- **No state** — immutable, stateless, reusable

### Action Index

| Action | Domain | Input | Calls |
|--------|--------|-------|-------|
| `LoginAction` | Auth | username, password | `AuthService::login()` |
| `RegisterAction` | Auth | username, password, name, email | `AuthService::register()` |
| `UserFetchAction` | User | Request | `UserClientInterface::getAll()` |
| `UserShowAction` | User | Request, id | `UserClientInterface::getById()` |
| `UserCreateAction` | User | Request | `UserClientInterface::create()` |
| `UserUpdateAction` | User | Request, id | `UserClientInterface::update()` |
| `UserDeleteAction` | User | Request, id | `UserClientInterface::remove()` |
| `ProductFetchAction` | Product | Request | `ProductClientInterface::getAll()` |
| `ProductShowAction` | Product | Request, id | `ProductClientInterface::getById()` |
| `ProductCreateAction` | Product | Request | `ProductClientInterface::create()` |
| `ProductUpdateAction` | Product | Request, id | `ProductClientInterface::update()` |
| `ProductDeleteAction` | Product | Request, id | `ProductClientInterface::remove()` |
| `OrderFetchAction` | Order | Request | `OrderClientInterface::getAll()` |
| `OrderShowAction` | Order | Request, id | `OrderClientInterface::getById()` |
| `OrderCreateAction` | Order | Request | `OrderClientInterface::create()` |
| `OrderUpdateStatusAction` | Order | Request, id | `OrderClientInterface::updateStatus()` |

## Step 11 — Presentation Layer: Controllers

Controllers are **ultra-thin entry points**. They follow this single pattern:

```php
public function index(Request $request, UserFetchAction $action)
{
    return $action($request);
}
```

That's it. The controller method:
1. Receives the HTTP request (validated by FormRequest for auth)
2. **Injects the action** as a method parameter (Laravel's IoC container resolves it)
3. Calls the action with the request/data
4. Returns the action's JsonResponse directly

No constructor dependencies. No business logic. No HTTP calls.

### AuthController

```php
class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action)
    {
        return $action(
            $request->input('username'),
            $request->input('password')
        );
    }

    public function register(RegisterRequest $request, RegisterAction $action)
    {
        return $action(
            $request->input('username'),
            $request->input('password'),
            $request->input('name'),
            $request->input('email')
        );
    }
}
```

### Per-Service Controllers

Each controller delegates entirely to actions — never touches clients, IdentityProvider, or DTOs directly:

```php
class UserController extends Controller
{
    public function index(Request $request, UserFetchAction $action)  { return $action($request); }
    public function show(Request $request, UserShowAction $action, int $id)    { return $action($request, $id); }
    public function store(Request $request, UserCreateAction $action) { return $action($request); }
    public function update(Request $request, UserUpdateAction $action, int $id) { return $action($request, $id); }
    public function destroy(Request $request, UserDeleteAction $action, int $id) { return $action($request, $id); }
}

class ProductController extends Controller
{
    public function index(Request $request, ProductFetchAction $action)  { return $action($request); }
    public function show(Request $request, ProductShowAction $action, int $id)    { return $action($request, $id); }
    public function store(Request $request, ProductCreateAction $action) { return $action($request); }
    public function update(Request $request, ProductUpdateAction $action, int $id) { return $action($request, $id); }
    public function destroy(Request $request, ProductDeleteAction $action, int $id) { return $action($request, $id); }
}

class OrderController extends Controller
{
    public function index(Request $request, OrderFetchAction $action)          { return $action($request); }
    public function show(Request $request, OrderShowAction $action, int $id)   { return $action($request, $id); }
    public function store(Request $request, OrderCreateAction $action)         { return $action($request); }
    public function updateStatus(Request $request, OrderUpdateStatusAction $action, int $id) { return $action($request, $id); }
}
```

### Form Request Validation (`app/Http/Requests/`)

Login and Register requests use Laravel Form Request classes:

```php
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
        ];
    }
}
```

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
OrderController::index(Request $request, OrderFetchAction $action)
  → return $action($request);
      │
      ▼
OrderFetchAction::__invoke($request)
      │
      ▼
IdentityProvider::getHeaders($request)
  → reads $request->user() → X-User-Id: 2, X-User-Role: user
      │
      ▼
OrderClientInterface::getAll($headers)
  → HttpOrderClient sends GET http://order-service:3003/api/orders
  → with X-User-Id and X-User-Role headers
      │
      ▼
Order Service responds with raw orders JSON
      │
      ▼
ResponseFormatter::map($body, function ($order) {
    return [
        'id'        => $order['id'],
        'customer'  => $order['customerName'],   // rename
        'itemCount' => count($order['items']),   // derive
        'total'     => $order['total'],
    ];
});
      │
      ▼
Formatted JSON response returned to browser
```

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
  │     → sets Auth::user()                    │
  │                                             │
  │  4. OrderController::index()                │
  │     → IdentityProvider::getHeaders()        │
  │       → X-User-Id: 2, X-User-Role: user    │
  │                                             │
  │  5. OrderClientInterface::getAll()          │
  │     → HttpOrderClient GET                   │
  │       http://order-service:3003/api/orders  │
  │       with X-User-Id / X-User-Role          │
  │                                             │
  │  6. OrderFetchAction transforms via         │
  │     ResponseFormatter::map()                │
  │     → customerName → customer              │
  │     → itemCount = count(items)             │
  └─────────────────────────────────────────────┘
              │
              ▼
  ┌─────────────────────────────┐
  │   Order Service :3003       │
  │  returns orders             │
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
| `502` | `ConnectionException` | `{"error":"Microservice unavailable","message":"..."}` |
| `500` | Any other exception | `{"error":"Server error","message":"..."}` |

---

## Environment Variables Reference

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
