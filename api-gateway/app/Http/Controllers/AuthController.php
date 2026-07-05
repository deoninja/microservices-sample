<?php

namespace App\Http\Controllers;

use App\Helpers\ProxyHelper;
use Firebase\JWT\JWT;
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

        // Step 3 — Build the JWT payload.
        //
        // The payload is the data we embed inside the token. Anyone can READ
        // a JWT payload (it's base64 encoded, not encrypted), but they cannot
        // MODIFY it without invalidating the signature.
        //
        // Standard JWT claims used here:
        //   'sub' (subject)  → the user's unique ID — identifies who the token belongs to
        //   'iat' (issued at) → Unix timestamp of when the token was created
        //   'exp' (expiry)   → Unix timestamp of when the token stops being valid
        $user   = $result['body'];
        $secret = config('services.jwt.secret');  // the signing key from .env
        $ttl    = config('services.jwt.ttl', 86400); // 86400 seconds = 24 hours

        $payload = [
            'sub'      => $user['id'],                // user ID — used as X-User-Id downstream
            'username' => $user['username'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role'] ?? 'user',    // 'admin' or 'user'
            'iat'      => time(),                     // current Unix timestamp
            'exp'      => time() + $ttl,              // expiry = now + 24 hours
        ];

        // Step 4 — Sign the token.
        //
        // JWT::encode() takes the payload, signs it with the secret using HS256
        // (HMAC-SHA256), and returns a compact token string in three parts:
        //   header.payload.signature
        // e.g. eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjF9.abc123
        $token = JWT::encode($payload, $secret, 'HS256');

        // Return the token and user data.
        // The frontend stores the token in localStorage and sends it as
        // "Authorization: Bearer <token>" on every subsequent request.
        return response()->json([
            'token' => $token,
            'user'  => $user,
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
        // This lets the client see the exact error message from the User Service.
        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        // Success — return the newly created user with HTTP 201 Created.
        return response()->json($result['body'], 201);
    }
}
