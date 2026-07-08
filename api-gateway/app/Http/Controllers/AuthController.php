<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/AuthController.php — Login & Registration (Refactored)
 *
 * SOLID Principles applied:
 *   - Single Responsibility: This controller only handles auth-related requests.
 *     User synchronization is the controller's concern (acting as the coordinator).
 *   - Dependency Inversion: Depends on UserService (abstraction) injected via
 *     constructor, not on static ProxyHelper calls.
 *   - Open/Closed: Adding new auth features (e.g. logout) means adding methods
 *     here or creating new classes, not modifying existing tested code.
 */

class AuthController extends Controller
{
    /**
     * The user microservice client.
     */
    private UserService $userService;

    /**
     * Inject the UserService dependency (DIP).
     *
     * Laravel's service container automatically resolves this from the
     * binding registered in AppServiceProvider.
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * POST /api/auth/login — Authenticate and return a Passport token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->userService->login([
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ]);

        if (!$result['success']) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $userData = $result['body'];

        $localUser = User::updateOrCreate(
            ['id' => $userData['id']],
            [
                'username' => $userData['username'],
                'name'     => $userData['name'],
                'email'    => $userData['email'],
                'role'     => $userData['role'] ?? 'user',
            ]
        );

        $token = $localUser->createToken('api-access-token')->accessToken;

        return response()->json([
            'token' => $token,
            'user'  => $userData,
        ]);
    }

    /**
     * POST /api/auth/register — Create a new user account.
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:6',
            'name'     => 'required|string',
            'email'    => 'required|email',
        ]);

        $result = $this->userService->register([
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
        ]);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        $userData = $result['body'];

        User::create([
            'id'       => $userData['id'],
            'username' => $userData['username'],
            'name'     => $userData['name'],
            'email'    => $userData['email'],
            'role'     => $userData['role'] ?? 'user',
        ]);

        return response()->json($userData, 201);
    }
}
