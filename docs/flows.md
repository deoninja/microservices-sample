# Flows — Login, Get Products, Create Order

This document explains exactly what happens step by step when you log in, fetch products, and create an order. Every layer the request passes through is shown.

---

## Flow 1 — Login

### What you send

```
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
  "username": "john",
  "password": "password"
}
```

### Step-by-step

```
1. Browser / curl sends POST /api/auth/login to the gateway

2. Laravel Kernel runs global middleware on the request:
   - HandleCors      → adds Access-Control-Allow-Origin header
   - ValidatePostSize → checks body isn't too large

3. RouteServiceProvider matches the route to AuthController::login()
   - No jwt.auth middleware on this route — login is public

4. AuthController::login() runs:

   a) Validates the request body
      - username: required, must be a string
      - password: required, must be a string
      - If either is missing → 422 Unprocessable Entity

   b) Reads USER_SERVICE_URL from config (http://user-service:3001 in Docker)

   c) ProxyHelper::forward() sends:
      POST http://user-service:3001/api/users/login
      Body: { "username": "john", "password": "password" }

5. User Service (Node.js :3001) receives the request:
   - Looks up john in the in-memory store by username
   - Compares the password
   - If wrong → returns 401 { "error": "Invalid credentials" }
   - If correct → returns 200 with the user object (password stripped):
     { "id": 2, "username": "john", "name": "John Doe",
       "email": "john@example.com", "role": "user", ... }

6. Back in AuthController:
   - If User Service returned an error → gateway returns 401 to the browser
   - If success → builds a JWT payload:
     {
       "sub":      2,           ← user ID
       "username": "john",
       "name":     "John Doe",
       "email":    "john@example.com",
       "role":     "user",
       "iat":      1720000000,  ← issued at (now)
       "exp":      1720086400   ← expires in 24 hours
     }

7. JWT::encode() signs the payload with JWT_SECRET using HS256
   Produces a token string: eyJ0eXAiOiJKV1Qi...

8. Gateway returns 200 to the browser:
   {
     "token": "eyJ0eXAiOiJKV1Qi...",
     "user": { "id": 2, "username": "john", ... }
   }

9. Frontend stores token in localStorage
   Every future request includes: Authorization: Bearer eyJ0eXAiOiJKV1Qi...
```

### What can go wrong

| Response | Reason |
|----------|--------|
| `422` | username or password field missing from request body |
| `401 Invalid credentials` | Wrong username or password |
| `502 Microservice unavailable` | User Service is not running |

---

## Flow 2 — Get Products

### What you send

```
GET http://localhost:8000/api/products
```

No token needed — this is a public endpoint.

Optional search filter:
```
GET http://localhost:8000/api/products?search=keyboard
```

### Step-by-step

```
1. Browser sends GET /api/products to the gateway

2. Laravel Kernel runs global middleware (CORS, ValidatePostSize)

3. RouteServiceProvider matches the route to GatewayController::getProducts()
   - No jwt.auth middleware on GET /products — it is public

4. GatewayController::getProducts() runs:

   a) Reads the ?search= query parameter (empty string if not provided)

   b) Builds the URL:
      - No search: http://product-service:3002/api/products
      - With search: http://product-service:3002/api/products?search=keyboard
        (urlencode() makes the search term URL-safe)

   c) getHeaders() returns an empty array — no JWT on this request,
      so no X-User-Id or X-User-Role headers are added

   d) ProxyHelper::forward() sends:
      GET http://product-service:3002/api/products

5. Product Service (Node.js :3002) receives the request:
   - If no search → returns all 5 products from the in-memory store
   - If search=keyboard → filters products where name contains "keyboard"
     (case-insensitive)
   - Returns 200 with a JSON array:
     [
       { "id": 4, "name": "Mechanical Keyboard", "price": 129.99,
         "description": "...", "stock": 25, "createdAt": "..." },
       ...
     ]

6. GatewayController returns the Product Service response directly to the browser
   - Same status code, same body — the gateway doesn't modify the data
```

### What can go wrong

| Response | Reason |
|----------|--------|
| `200` with empty array `[]` | No products match the search term |
| `502 Microservice unavailable` | Product Service is not running |

---

## Flow 3 — Create Order

### What you send

You must be logged in. Include the token from login.

```
POST http://localhost:8000/api/orders
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
Content-Type: application/json

{
  "customerName": "John Doe",
  "items": [
    { "name": "Mechanical Keyboard", "quantity": 1, "price": 129.99 },
    { "name": "USB-C Hub",           "quantity": 2, "price": 34.99  }
  ]
}
```

### Step-by-step

```
1. Browser sends POST /api/orders with Authorization header

2. Laravel Kernel runs global middleware (CORS, ValidatePostSize)

3. RouteServiceProvider matches the route — POST /orders has jwt.auth middleware

4. JwtMiddleware runs BEFORE the controller:

   a) Reads the Authorization header → extracts the Bearer token

   b) If no token → returns 401 "Authentication required" immediately
      (controller never runs)

   c) JWT::decode() verifies the token:
      - Checks the signature using JWT_SECRET
        (confirms the token was issued by this gateway, not forged)
      - Checks the exp claim — rejects if expired
      - Decodes the payload into a PHP object

   d) If invalid → returns 401 "Invalid or expired token"

   e) If valid → attaches user data to the request:
      $request->attributes->set('jwt_user', ['sub'=>2, 'role'=>'user', ...])
      $request->headers->set('X-User-Id',   '2')
      $request->headers->set('X-User-Role', 'user')

5. GatewayController::createOrder() runs:

   a) getHeaders() reads jwt_user from request attributes:
      returns ['X-User-Id' => '2', 'X-User-Role' => 'user']

   b) ProxyHelper::forward() sends:
      POST http://order-service:3003/api/orders
      Headers: X-User-Id: 2, X-User-Role: user
      Body: { "customerName": "John Doe", "items": [...] }

6. Order Service (Node.js :3003) receives the request:

   a) Reads X-User-Id from headers → this becomes the order's userId
      (users can't fake this — it comes from the verified JWT)

   b) Validates the items array:
      - Must be a non-empty array
      - Each item needs name, quantity (≥1), and price

   c) Calculates the total:
      (1 × 129.99) + (2 × 34.99) = 199.97
      Math.round(199.97 × 100) / 100 = 199.97

   d) Creates the order in the in-memory store:
      {
        "id":           4,
        "userId":       2,
        "customerName": "John Doe",
        "items":        [...],
        "total":        199.97,
        "status":       "pending",
        "createdAt":    "2024-07-04T12:00:00.000Z"
      }

   e) Returns 201 Created with the new order object

7. GatewayController returns the Order Service response to the browser
   - 201 status, full order object in the body
```

### What can go wrong

| Response | Reason |
|----------|--------|
| `401 Authentication required` | No Authorization header sent |
| `401 Invalid or expired token` | Token is wrong, tampered, or older than 24h — log in again |
| `400 Order must contain at least one item` | items array is empty or missing |
| `400 Each item requires name, quantity, and price` | An item is missing a field |
| `400 Item quantity must be at least 1` | quantity is 0 or negative |
| `502 Microservice unavailable` | Order Service is not running |

---

## How the three flows connect

```
Login                    Get Products             Create Order
─────                    ────────────             ────────────
POST /api/auth/login     GET /api/products        POST /api/orders
        │                       │                        │
        │                  No token needed          Needs token
        │                       │                   from login
        ▼                       ▼                        ▼
  User Service           Product Service          JwtMiddleware
  verifies password      returns catalog          verifies token
        │                       │                        │
  Gateway signs                 │                 Order Service
  JWT token                     │                 reads X-User-Id
        │                       │                 creates order
        ▼                       ▼                        ▼
  { token, user }        [ ...products ]          { id, total, status }
        │
  Store in localStorage
  Use on every future request
```

---

## Quick reference — request format

### Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"john","password":"password"}'
```

### Get all products
```bash
curl http://localhost:8000/api/products
```

### Get products with search
```bash
curl "http://localhost:8000/api/products?search=keyboard"
```

### Create order (replace TOKEN with value from login)
```bash
curl -X POST http://localhost:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "customerName": "John Doe",
    "items": [
      {"name": "Mechanical Keyboard", "quantity": 1, "price": 129.99}
    ]
  }'
```
