<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;

/*
 * app/Http/Controllers/AuthController.php — Authentication Entry Point
 *
 * Clean Architecture — Presentation Layer:
 *   This controller is a THIN entry point. It only:
 *   1. Receives the HTTP request
 *   2. Validates input (via Form Request classes)
 *   3. Delegates to the Application layer (AuthService)
 *   4. Returns the response
 *
 * No business logic lives here. Authentication orchestration is in
 * AuthService (Application layer). HTTP communication is in
 * HttpUserClient (Infrastructure layer).
 */

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login(
            $request->input('username'),
            $request->input('password')
        );

        if (!$result['success']) {
            return response()->json($result['body'], $result['status'] ?? 401);
        }

        return response()->json([
            'token' => $result['token'],
            'user'  => $result['user']->toArray(),
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register(
            $request->input('username'),
            $request->input('password'),
            $request->input('name'),
            $request->input('email')
        );

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']->toArray(), 201);
    }
}
