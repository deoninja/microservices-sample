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
    │  2. JwtMiddleware validates the Bearer token (on protected routes)
    │  3. GatewayController builds the microservice URL
    │  4. ProxyHelper sends the HTTP request via Guzzle
    │
    ├──► User Service (:3001)    — users, login, register
    ├──► Product Service (:3002) — product catalog
    └──► Order Service (:3003)   — orders and status
```

## Key concepts to understand

- **JWT flow** — login returns a token → stored in localStorage → attached to every request by the Axios interceptor → verified by JwtMiddleware → user identity forwarded as `X-User-Id` / `X-User-Role` headers to microservices
- **Proxy pattern** — the gateway never stores data itself; it only forwards requests and returns responses
- **In-memory stores** — all three Node.js services use plain JavaScript arrays as their "database"; data resets on restart
- **Public vs protected routes** — product GET routes are public; everything else requires a JWT
