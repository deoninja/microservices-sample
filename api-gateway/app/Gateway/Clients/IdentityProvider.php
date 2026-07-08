<?php

namespace App\Gateway\Clients;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
 * app/Gateway/Clients/IdentityProvider.php — User Identity Header Builder
 *
 * Infrastructure adapter that reads the authenticated user from the
 * request/auth context and builds the identity headers (X-User-Id,
 * X-User-Role) to forward to microservices.
 *
 * In Clean Architecture terms, this is an Infrastructure-level adapter
 * that sits between the Presentation (controllers) and the external
 * microservices. It translates Laravel's Auth system into headers
 * that downstream services understand.
 */

class IdentityProvider
{
    /**
     * Build identity headers from the authenticated user.
     *
     * Returns an empty array for unauthenticated (public) requests.
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
