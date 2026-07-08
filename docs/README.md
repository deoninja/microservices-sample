# Documentation Index

Code walkthroughs for every file in the MicroStore project.  
Each doc explains what the file does, why it's written the way it is, and what every line means.

---

## Docs

| File | What it covers |
|------|----------------|
| [api-gateway.md](./api-gateway.md) | Laravel API Gateway — Clean Architecture: Presentation, Application, Infrastructure layers |
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
    │  PRESENTATION LAYER (Controllers + FormRequests)
    │    1. CORS middleware checks the origin
    │    2. auth:api middleware validates the Bearer token via Passport
    │    3. Controllers delegate to Application/Infrastructure layers
    │
    │  APPLICATION LAYER (AuthService)
    │    4. AuthService orchestrates login/register across clients + Passport
    │
    │  INFRASTRUCTURE LAYER (Http*Clients, IdentityProvider, DTOs)
    │    5. Http*Client sends HTTP requests via Laravel Http facade
    │    6. DTOs (UserData, ProductData, OrderData) type-check the data
    │    7. IdentityProvider builds X-User-Id / X-User-Role headers
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
- **Clean Architecture layers** — Presentation (controllers), Application (AuthService), Infrastructure (clients, DTOs). Dependencies always point inward.

## Recent changes

### Clean Architecture Refactoring

The API Gateway was refactored from SOLID services to a full Clean Architecture structure:

| Layer | Directory | Purpose |
|-------|-----------|---------|
| **Presentation** | `app/Http/Controllers/` | Thin entry points — validate, delegate, return |
| **Presentation** | `app/Http/Requests/` | Input validation via Form Request classes |
| **Application** | `app/Services/` | Orchestration — AuthService coordinates login/register |
| **Infrastructure** | `app/Gateway/Contracts/` | Ports — interfaces defining service contracts |
| **Infrastructure** | `app/Gateway/Clients/` | Adapters — concrete HTTP implementations using Laravel Http facade |
| **Infrastructure** | `app/Gateway/Clients/IdentityProvider.php` | Builds X-User-Id / X-User-Role headers |
| **Infrastructure** | `app/Gateway/DTOs/` | Typed data transfer objects (UserData, ProductData, OrderData) |

**New files:**
- `app/Gateway/Contracts/UserClientInterface.php`, `ProductClientInterface.php`, `OrderClientInterface.php`
- `app/Gateway/Clients/HttpUserClient.php`, `HttpProductClient.php`, `HttpOrderClient.php`
- `app/Gateway/Clients/IdentityProvider.php` — moved from `app/Auth/`
- `app/Gateway/DTOs/UserData.php`, `ProductData.php`, `OrderData.php`
- `app/Services/AuthService.php` — orchestration layer
- `app/Http/Requests/LoginRequest.php`, `RegisterRequest.php` — Form Request validation

**Removed:**
- `app/Contracts/ServiceClientInterface.php` — replaced by per-service interfaces in `Gateway/Contracts/`
- `app/Services/BaseService.php` — replaced by `Gateway/Clients/Http*Client` classes
- `app/Services/UserService.php`, `ProductService.php`, `OrderService.php` — replaced by `Gateway/Clients/`
- `app/Auth/IdentityProvider.php` — moved to `Gateway/Clients/`
- `app/Helpers/ProxyHelper.php` — replaced by `Http*Client` classes
- `app/Http/Controllers/GatewayController.php` — split into separate controllers per service

### Fixed: Error handling for microservice connection failures
Added `ConnectionException` handling to `app/Exceptions/Handler.php` — returns `502 Bad Gateway` when a microservice is unreachable.

### Fixed: `Target class [auth] does not exist`
Added `Illuminate\Auth\AuthServiceProvider` to `config/app.php` providers list.

### Fixed: Auth middleware alias
Added `'auth' => \Illuminate\Auth\Middleware\Authenticate::class` to `app/Http/Kernel.php`.

### Fixed: 401 instead of 500 for unauthenticated requests
Added `AuthenticationException` handling to `app/Exceptions/Handler.php`.

### Fixed: APP_KEY missing in Docker
Added `APP_KEY` to `docker-compose.yml` for the api-gateway service.
