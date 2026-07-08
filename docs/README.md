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

### Fixed: `Target class [auth] does not exist`

The `Illuminate\Auth\AuthServiceProvider` was missing from the providers list in `config/app.php`. This provider registers the `auth` singleton in the container, which is required by `Auth::user()`, `$request->user()`, and the `auth:api` middleware.

### Fixed: Auth middleware alias

The `auth` middleware alias (`\Illuminate\Auth\Middleware\Authenticate`) was added to `app/Http/Kernel.php` so that `middleware('auth:api')` can be resolved in routes.

### Fixed: 401 instead of 500 for unauthenticated requests

The exception handler (`app/Exceptions/Handler.php`) now catches `AuthenticationException` and returns a `401` JSON response instead of a generic `500`.

### Fixed: APP_KEY missing in Docker

The `APP_KEY` environment variable was added to `docker-compose.yml` so the Laravel encryption key is available in Docker (the `.env` file is deleted during the Docker build). The fallback key in `config/app.php` was also fixed to be a valid 32-byte key.
