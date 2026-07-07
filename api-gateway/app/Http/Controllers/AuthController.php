<?php

namespace App\Http\Controllers;

use App\Helpers\ProxyHelper;
use App\Models\User;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/AuthController.php — Login & Registration
 *
 * This controller handles the two public auth endpoints:
 *   POST /api/auth/login    → verifies credentials, returns a JWT token
 *   POST /api/auth/register → creates a new user account
 *
 * It does NOT store users itself. Instead it forwards the request to
 * the User Service (Node.js on port 3001) which owns the user data.
 * The gateway's only unique job here is to ISSUE the JWT token after
 * the User Service confirms the credentials are correct.
 */

class AuthController extends Controller
{
    /*
     * login() — Authenticate a user and return a JWT token.
     *
     * Flow:
     *   1. Validate the request body
     *   2. Forward credentials to User Service for verification
     *   3. If valid, build a JWT payload and sign it
     *   4. Return the token + user data to the client
     */
    public function login(Request $request)
    {
        // Step 1 — Validate the incoming request body.
        // Laravel throws a 422 response automatically if these rules fail.
        // 'required|string' means the field must be present and a string.
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Step 2 — Forward the credentials to the User Service.
        //
        // ProxyHelper::serviceUrl() reads the URL from config/services.php
        // which gets it from the USER_SERVICE_URL environment variable.
        // Result: http://localhost:3001 (local) or http://user-service:3001 (Docker)
        $serviceUrl = ProxyHelper::serviceUrl('user_service');

        // Send a POST request to the User Service login endpoint.
        // The User Service checks if the username/password match a stored user
        // and returns the user object if they do, or an error if they don't.
        $result = ProxyHelper::forward('POST', "{$serviceUrl}/api/users/login", [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ]);

        // If the User Service returned an error (wrong credentials, service down, etc.)
        // return 401 to the client. We don't expose the internal error details.
        if (!$result['success']) {
            return response()->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        // Step 3 — Find or create a local user record for Passport.
        //
        // Passport issues tokens against a local User model, so we need a
        // matching record in our SQLite database. The User Service is the
        // source of truth for credentials — we only store enough metadata
        // (id, username, name, email, role) to issue and validate tokens.
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

        // Step 4 — Issue a Passport personal access token.
        //
        // createToken() generates a new access token tied to this user,
        // stores it in the oauth_access_tokens table, and returns the
        // plain-text token exactly once in the response.
        $token = $localUser->createToken('api-access-token')->accessToken;

        return response()->json([
            'token' => $token,
            'user'  => $userData,
        ]);
    }

    /*
     * register() — Create a new user account.
     *
     * Flow:
     *   1. Validate the request body (stricter rules than login)
     *   2. Forward the data to the User Service to create the account
     *   3. Return the new user object or pass through any error
     *
     * Note: register does NOT return a JWT token. The user must call
     * /api/auth/login after registering to get a token.
     */
    public function register(Request $request)
    {
        // Step 1 — Validate with stricter rules than login.
        // min:3 and min:6 enforce minimum lengths.
        // 'email' rule checks it's a valid email format.
        $request->validate([
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:6',
            'name'     => 'required|string',
            'email'    => 'required|email',
        ]);

        // Step 2 — Forward the registration data to the User Service.
        // The User Service checks for duplicate usernames and creates the account.
        $serviceUrl = ProxyHelper::serviceUrl('user_service');
        $result = ProxyHelper::forward('POST', "{$serviceUrl}/api/users/register", [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
        ]);

        // Step 3 — If the User Service returned an error (e.g. username already taken),
        // pass the error response through with the original status code.
        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        // Step 4 — Create a matching local user record for Passport.
        $userData = $result['body'];
        User::create([
            'id'       => $userData['id'],
            'username' => $userData['username'],
            'name'     => $userData['name'],
            'email'    => $userData['email'],
            'role'     => $userData['role'] ?? 'user',
        ]);

        // Return the newly created user with HTTP 201 Created.
        return response()->json($userData, 201);
    }
}
