# MicroStore — Microservices Demo

A full-stack microservices application with a React frontend, Laravel API Gateway, and three Node.js microservices.

## Architecture

```
Browser (React)  :3000
      │
      ▼
Laravel API Gateway  :8000   ← JWT auth, request proxying
      │
      ├── User Service    :3001  (Node.js / Express)
      ├── Product Service :3002  (Node.js / Express)
      └── Order Service   :3003  (Node.js / Express)
```

## Prerequisites

### Option A — Docker (recommended)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (includes Docker Compose)

### Option B — Local (without Docker)
- [Node.js](https://nodejs.org/) v18+
- [PHP](https://www.php.net/downloads) 8.1+
- [Composer](https://getcomposer.org/)

---

## Option A: Run with Docker

### 1. Clone / open the project
```bash
cd Microservices
```

### 2. Build and start all containers
```bash
docker compose up --build
```

> First build takes ~3–5 minutes. Subsequent starts are instant.

### 3. Open the app
| Service         | URL                              |
|-----------------|----------------------------------|
| Frontend        | http://localhost:3000            |
| API Gateway     | http://localhost:8000/api/health |
| User Service    | http://localhost:3001/api/health |
| Product Service | http://localhost:3002/api/health |
| Order Service   | http://localhost:3003/api/health |

### Stop containers
```bash
docker compose down
```

### Rebuild after code changes
```bash
docker compose up --build
```

---

## Option B: Run Locally (Windows)

### 1. Install Node dependencies for each service
```powershell
cd services\user-service    && npm install && cd ..\..
cd services\product-service && npm install && cd ..\..
cd services\order-service   && npm install && cd ..\..
cd frontend                 && npm install && cd ..
```

### 2. Install PHP dependencies for the API Gateway
```powershell
cd api-gateway
composer install
cd ..
```

### 3. Start everything with one command
```powershell
.\start.ps1
```

> If you get a script execution error, run this first:
> ```powershell
> Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
> ```

### 4. Open the app
| Service         | URL                              |
|-----------------|----------------------------------|
| Frontend        | http://localhost:3000            |
| API Gateway     | http://localhost:8000/api/health |
| User Service    | http://localhost:3001/api/health |
| Product Service | http://localhost:3002/api/health |
| Order Service   | http://localhost:3003/api/health |

Press `Ctrl+C` in the terminal to stop all services.

---

## Option C: Run Services Manually (any OS)

Open a separate terminal for each service:

**Terminal 1 — User Service**
```bash
cd services/user-service
npm install
node src/index.js
```

**Terminal 2 — Product Service**
```bash
cd services/product-service
npm install
node src/index.js
```

**Terminal 3 — Order Service**
```bash
cd services/order-service
npm install
node src/index.js
```

**Terminal 4 — API Gateway**
```bash
cd api-gateway
composer install
php artisan serve --host=localhost --port=8000
```

**Terminal 5 — Frontend**
```bash
cd frontend
npm install
npm run dev
```

---

## Demo Credentials

| Username | Password | Role  |
|----------|----------|-------|
| admin    | password | admin |
| john     | password | user  |

---

## Project Structure

```
Microservices/
├── api-gateway/          # Laravel 10 — JWT auth + request proxy
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── AuthController.php   # login / register
│   │   │   │   └── GatewayController.php # proxy to microservices
│   │   │   └── Middleware/
│   │   │       └── JwtMiddleware.php
│   │   └── Helpers/
│   │       └── ProxyHelper.php          # Guzzle HTTP forwarding
│   ├── routes/api.php
│   └── .env
├── services/
│   ├── user-service/     # Node.js :3001 — users + auth
│   ├── product-service/  # Node.js :3002 — product catalog
│   └── order-service/    # Node.js :3003 — order management
├── frontend/             # React + Vite + TypeScript :3000
├── docker-compose.yml
└── start.ps1             # Windows one-click startup script
```

## API Endpoints

All requests go through the gateway at `http://localhost:8000/api`.

| Method | Endpoint              | Auth | Description          |
|--------|-----------------------|------|----------------------|
| POST   | /auth/login           | No   | Login, returns JWT   |
| POST   | /auth/register        | No   | Register new user    |
| GET    | /products             | No   | List all products    |
| GET    | /products/:id         | No   | Get single product   |
| POST   | /products             | Yes  | Create product       |
| PUT    | /products/:id         | Yes  | Update product       |
| DELETE | /products/:id         | Yes  | Delete product       |
| GET    | /orders               | Yes  | List orders          |
| POST   | /orders               | Yes  | Create order         |
| PUT    | /orders/:id/status    | Yes  | Update order status  |
| GET    | /users                | Yes  | List users           |

> **Auth** = requires `Authorization: Bearer <token>` header.
