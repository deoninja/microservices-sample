<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * app/Http/Middleware/JwtMiddleware.php — JWT Authentication Guard
 *
 * This middleware runs before any protected controller method.
 * Its only job is to answer: "Is this request coming from a valid logged-in user?"
 *
 * It is registered as the 'jwt.auth' alias in Kernel.php and applied to
 * protected routes in routes/api.php like this:
 *   Route::get('/orders')->middleware('jwt.auth')
 *
 * Flow:
 *   1. Extract the Bearer token from the Authorization header
 *   2. Decode and verify it using the JWT_SECRET
 *   3. If valid → attach user data to the request and continue
 *   4. If missing or invalid → return 401 immediately, block the request
 */

class JwtMiddleware
{
    /*
     * handle() is called automatically by Laravel for every request
     * that has this middleware attached.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Closure  $next     The next middleware or controller in the pipeline
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Step 1 — Extract the token from the Authorization header.
        //
        // bearerToken() looks for the header: "Authorization: Bearer <token>"
        // and returns just the token string. Returns null if the header is
        // missing or not in Bearer format.
        $token = $request->bearerToken();

        // If no token was provided at all, reject immediately with 401.
        // The client needs to log in first to get a token.
        if (!$token) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        try {
            // Step 2 — Decode and verify the token.
            //
            // JWT::decode() does three things automatically:
            //   a) Verifies the token signature using the secret key
            //      (ensures the token was created by this gateway, not forged)
            //   b) Checks the 'exp' (expiry) claim — rejects expired tokens
            //   c) Decodes the payload into a PHP object
            //
            // new Key($secret, 'HS256') tells the library which algorithm was
            // used to sign the token. HS256 = HMAC with SHA-256.
            $secret  = config('services.jwt.secret');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Step 3 — Attach the decoded user data to the request.
            //
            // We store the full user object as 'jwt_user' in request attributes.
            // GatewayController reads this later to build the X-User-Id header.
            // (array) cast converts the stdClass object JWT returns into an array.
            $request->attributes->set('jwt_user', (array) $decoded);

            // Also set two request headers that will be forwarded to microservices.
            // This way microservices know WHO is making the request without
            // needing to decode the JWT themselves.
            //
            //   X-User-Id   → the user's numeric ID (from the 'sub' claim)
            //   X-User-Role → 'admin' or 'user' (used by Order Service for filtering)
            $request->headers->set('X-User-Id',   $decoded->sub);
            $request->headers->set('X-User-Role',  $decoded->role ?? 'user');

        } catch (\Exception $e) {
            // Step 4 — Token was present but invalid.
            //
            // Common reasons JWT::decode() throws:
            //   - Signature mismatch (token was tampered with or wrong secret)
            //   - Token has expired ('exp' timestamp is in the past)
            //   - Token is malformed (not a valid JWT string)
            //
            // We return 401 with the exception message to help with debugging.
            return response()->json([
                'error'   => 'Invalid or expired token',
                'message' => $e->getMessage(),
            ], 401);
        }

        // Step 5 — Token is valid. Pass the request to the next layer
        // (either another middleware or the controller method).
        return $next($request);
    }
}
