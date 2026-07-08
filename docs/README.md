# Documentation Index

Code walkthroughs for every file in the MicroStore project.  
Each doc explains what the file does, why it's written the way it is, and what every line means.

---

## Docs

| File | What it covers |
|------|----------------|
| [api-gateway.md](./api-gateway.md) | Laravel API Gateway — boot process, middleware, JWT auth, routing, proxying |
| [user-service.md](./user-service.md) | User Service (Node.js) — Express setup, in-memory store, login/register, CRUD routes |
| [product-service.md](./product-service.md) | Product Service (Node.js) — Express setup, product store, search, CRUD routes |
| [order-service.md](./order-service.md) | Order Service (Node.js) — Express setup, order store, ownership checks, status updates |
| [frontend.md](./frontend.md) | React Frontend — Vite config, Axios client, auth interceptors, all pages and components |

---

## How the pieces connect

```
Browser (React :3000)
    │
    │  All requests go to /api/*
    │  Vite proxy forwards them to the gateway
    ▼
Laravel API Gateway (:8000)
    │
    │  1. CORS middleware checks the origin
    │  2. auth:api middleware validates the Bearer token via Passport
    │     (on protected routes — products GET routes are public)
    │  3. GatewayController reads authenticated user via $request->user()
    │  4. ProxyHelper sends the HTTP request via Guzzle with X-User-Id headers
    │
    ├──► User Service (:3001)    — users, login, register
    ├──► Product Service (:3002) — product catalog
    └──► Order Service (:3003)   — orders and status
```

## Key concepts to understand

- **Passport auth** — login returns a Passport personal access token → stored in localStorage → attached to every request by the Axios interceptor → verified by `auth:api` middleware via Passport's OAuth2 guard → user identity forwarded as `X-User-Id` / `X-User-Role` headers to microservices
- **Proxy pattern** — the gateway never stores data itself; it only forwards requests and returns responses
- **In-memory stores** — all three Node.js services use plain JavaScript arrays as their "database"; data resets on restart
- **Public vs protected routes** — product GET routes are public; everything else requires a Bearer token validated by Passport

## Recent changes

### SOLID Refactoring

The API Gateway was refactored to follow SOLID principles:

| Principle | How it was applied |
|-----------|-------------------|
| **S**ingle Responsibility | Each microservice has its own controller (`UserController`, `ProductController`, `OrderController`) and service (`UserService`, `ProductService`, `OrderService`). Identity header building extracted to `IdentityProvider`. |
| **O**pen/Closed | New microservices can be added by creating new controllers and services without modifying existing code. |
| **L**iskov Substitution | All service classes extend `BaseService` and implement `ServiceClientInterface` — they can be swapped freely. |
| **I**nterface Segregation | Each controller has only the methods it needs. No monolithic `GatewayController` with unrelated methods. |
| **D**ependency Inversion | Controllers depend on injected service abstractions (`UserService`, `ProductService`, etc.) via constructor injection, not on static helper calls. |

**New file structure:**
- `app/Contracts/ServiceClientInterface.php` — contract for all microservice clients
- `app/Services/BaseService.php` — abstract HTTP client with shared Guzzle logic
- `app/Services/UserService.php`, `ProductService.php`, `OrderService.php` — one service per microservice
- `app/Auth/IdentityProvider.php` — builds X-User-Id / X-User-Role headers
- `app/Http/Controllers/UserController.php`, `ProductController.php`, `OrderController.php` — one controller per service

**Removed:**
- `app/Helpers/ProxyHelper.php` — replaced by `BaseService`
- `app/Http/Controllers/GatewayController.php` — split into separate controllers per service
- `routes/api.php` — updated to use new controllers

### Fixed: `Target class [auth] does not exist`
Added `Illuminate\Auth\AuthServiceProvider` to `config/app.php` providers list.

### Fixed: Auth middleware alias
Added `'auth' => \Illuminate\Auth\Middleware\Authenticate::class` to `app/Http/Kernel.php`.

### Fixed: 401 instead of 500 for unauthenticated requests
Added `AuthenticationException` handling to `app/Exceptions/Handler.php`.

### Fixed: APP_KEY missing in Docker
Added `APP_KEY` to `docker-compose.yml` for the api-gateway service.
