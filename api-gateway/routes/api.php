<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

/*
 * routes/api.php — API Gateway Route Definitions
 *
 * All routes here are automatically prefixed with /api by RouteServiceProvider.
 * So Route::get('/health') is reachable at GET http://localhost:8000/api/health.
 *
 * Each microservice has its own controller, following the Single Responsibility
 * Principle:
 *   - AuthController   → login & registration
 *   - UserController   → user CRUD (proxied to User Service)
 *   - ProductController → product CRUD (proxied to Product Service)
 *   - OrderController   → order CRUD (proxied to Order Service)
 *
 * Protected routes use the 'auth:api' middleware which validates the
 * Bearer token via Passport before the controller runs.
 */

// ── Health Check ──────────────────────────────────────────────────────────────
Route::get('/health', function () {
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    return response()->json([
        'status'    => 'ok',
        'service'   => 'api-gateway',
        'timestamp' => $now->format('Y-m-d\TH:i:s\Z'),
    ]);
});

// ── Authentication Routes — NO token required ─────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// ── User Routes — ALL protected by Passport ───────────────────────────────────
Route::prefix('users')->middleware('auth:api')->group(function () {
    Route::get('/',           [UserController::class, 'index']);   // list all users
    Route::get('/{id}',       [UserController::class, 'show']);    // get one user by ID
    Route::post('/',          [UserController::class, 'store']);   // create a new user
    Route::put('/{id}',       [UserController::class, 'update']);  // update a user
    Route::delete('/{id}',    [UserController::class, 'destroy']); // delete a user
});

// ── Product Routes — read is PUBLIC, write is PROTECTED ──────────────────────
Route::prefix('products')->group(function () {
    Route::get('/',          [ProductController::class, 'index']);   // public — list products
    Route::get('/{id}',      [ProductController::class, 'show']);    // public — get one product

    // The three write routes require a token
    Route::post('/',         [ProductController::class, 'store'])->middleware('auth:api');
    Route::put('/{id}',      [ProductController::class, 'update'])->middleware('auth:api');
    Route::delete('/{id}',   [ProductController::class, 'destroy'])->middleware('auth:api');
});

// ── Order Routes — ALL protected by Passport ─────────────────────────────────
Route::prefix('orders')->middleware('auth:api')->group(function () {
    Route::get('/',                  [OrderController::class, 'index']);        // list orders
    Route::get('/{id}',              [OrderController::class, 'show']);         // get one order
    Route::post('/',                 [OrderController::class, 'store']);        // place new order
    Route::put('/{id}/status',       [OrderController::class, 'updateStatus']); // update status
});
