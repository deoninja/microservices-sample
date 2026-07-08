<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
 * app/Auth/IdentityProvider.php — User Identity Header Builder
 *
 * This class has a SINGLE RESPONSIBILITY (SRP): building the identity
 * headers (X-User-Id, X-User-Role) that get forwarded to microservices.
 *
 * By extracting this from the controllers, we:
 *   - Isolate the logic for easy testing
 *   - Keep controllers focused on request/response handling (SRP)
 *   - Make the header-building logic reusable across all controllers (DRY)
 *
 * This class depends on Laravel's Auth system (via DI) but the controllers
 * depend on this abstraction, not on Auth directly (DIP).
 */

class IdentityProvider
{
    /**
     * Build identity headers from the authenticated user.
     *
     * For public routes where no user is authenticated, returns an empty array.
     * For protected routes, returns X-User-Id and X-User-Role headers.
     *
     * @return array<string, string> e.g. ['X-User-Id' => '2', 'X-User-Role' => 'user']
     */
    public function getHeaders(Request $request): array
    {
        $headers = [];

        $user = $request->user() ?? Auth::user();

        if ($user) {
            $headers['X-User-Id']   = (string) $user->id;
            $headers['X-User-Role'] = $user->role ?? 'user';
        }

        return $headers;
    }
}
