<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

/*
 * routes/api.php — API Gateway Route Definitions
 *
 * All routes here are automatically prefixed with /api by RouteServiceProvider.
 * So Route::get('/health') is reachable at GET http://localhost:8000/api/health.
 *
 * Routes either go directly to a controller method (AuthController for login/register)
 * or to GatewayController which proxies the request to the correct microservice.
 *
 * Protected routes use the 'jwt.auth' middleware alias defined in Kernel.php.
 * That middleware reads and validates the Bearer token before the controller runs.
 */

// ── Health Check ──────────────────────────────────────────────────────────────
//
// A simple ping endpoint to confirm the gateway is running.
// No authentication needed — used by Docker health checks and monitoring tools.
// Returns: { "status": "ok", "service": "api-gateway", "timestamp": "..." }
Route::get('/health', function () {
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    return response()->json([
        'status'    => 'ok',
        'service'   => 'api-gateway',
        'timestamp' => $now->format('Y-m-d\TH:i:s\Z'),
    ]);
});

// ── Authentication Routes — NO token required ─────────────────────────────────
//
// These are the only routes where a user doesn't need to be logged in yet.
// prefix('auth') means both routes start with /api/auth/...
//
//   POST /api/auth/login    → AuthController::login()
//   POST /api/auth/register → AuthController::register()
//
// On successful login, AuthController returns a JWT token the client stores
// and sends with every subsequent protected request.
Route::prefix('auth')->group(function () {
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// ── User Routes — ALL protected by JWT ───────────────────────────────────────
//
// middleware('jwt.auth') on the group means EVERY route inside requires a valid token.
// JwtMiddleware runs first — if the token is missing or invalid, it returns 401
// immediately and the controller never executes.
//
// These proxy to User Service at http://localhost:3001/api/users/...
Route::prefix('users')->middleware('jwt.auth')->group(function () {
    Route::get('/',      [GatewayController::class, 'getUsers']);   // list all users
    Route::get('/{id}',  [GatewayController::class, 'getUser']);    // get one user by ID
    Route::post('/',     [GatewayController::class, 'createUser']); // create a new user
    Route::put('/{id}',  [GatewayController::class, 'updateUser']); // update a user
    Route::delete('/{id}', [GatewayController::class, 'deleteUser']); // delete a user
});

// ── Product Routes — read is PUBLIC, write is PROTECTED ──────────────────────
//
// Anyone can browse products without logging in (good for a storefront).
// But creating, editing, or deleting products requires a valid JWT token.
//
// The group has NO middleware — the two GET routes are fully public.
// The POST/PUT/DELETE routes each add ->middleware('jwt.auth') individually.
//
// These proxy to Product Service at http://localhost:3002/api/products/...
Route::prefix('products')->group(function () {
    Route::get('/',      [GatewayController::class, 'getProducts']); // public — list products
    Route::get('/{id}',  [GatewayController::class, 'getProduct']);  // public — get one product

    // The three write routes require a token — middleware is applied per-route here
    Route::post('/',     [GatewayController::class, 'createProduct'])->middleware('jwt.auth');
    Route::put('/{id}',  [GatewayController::class, 'updateProduct'])->middleware('jwt.auth');
    Route::delete('/{id}', [GatewayController::class, 'deleteProduct'])->middleware('jwt.auth');
});

// ── Order Routes — ALL protected by JWT ──────────────────────────────────────
//
// Orders always require authentication — you must be logged in to view or place orders.
// The Order Service uses the X-User-Id header (set by JwtMiddleware) to filter
// orders so regular users only see their own, while admins see all.
//
// These proxy to Order Service at http://localhost:3003/api/orders/...
Route::prefix('orders')->middleware('jwt.auth')->group(function () {
    Route::get('/',              [GatewayController::class, 'getOrders']);       // list orders
    Route::get('/{id}',          [GatewayController::class, 'getOrder']);        // get one order
    Route::post('/',             [GatewayController::class, 'createOrder']);     // place new order
    Route::put('/{id}/status',   [GatewayController::class, 'updateOrderStatus']); // update status
});
