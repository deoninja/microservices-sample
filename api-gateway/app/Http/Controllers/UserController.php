<?php

namespace App\Http\Controllers;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\UserClientInterface;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/UserController.php — User Endpoint (Presentation Layer)
 *
 * Clean Architecture:
 *   Presentation Layer — Thin entry point. Only:
 *   1. Receives HTTP request
 *   2. Delegates to Infrastructure layer (UserClientInterface)
 *   3. Returns JSON response
 *
 * No business logic, no HTTP calls, no service orchestration.
 * All of that lives in the Application (AuthService) or Infrastructure (Clients) layers.
 */

class UserController extends Controller
{
    private UserClientInterface $userClient;
    private IdentityProvider $identityProvider;

    public function __construct(UserClientInterface $userClient, IdentityProvider $identityProvider)
    {
        $this->userClient = $userClient;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/users — List all users.
     */
    public function index(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->getAll($headers);

        return response()->json(
            $this->serializeCollection($result['body']),
            $result['status']
        );
    }

    /**
     * GET /api/users/{id} — Get a single user.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->getById($id, $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * POST /api/users — Create a new user.
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->create($request->all(), $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * PUT /api/users/{id} — Update a user.
     */
    public function update(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->update($id, $request->all(), $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * DELETE /api/users/{id} — Delete a user.
     */
    public function destroy(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->remove($id, $headers);

        return response()->json($result['body'], $result['status']);
    }

}

