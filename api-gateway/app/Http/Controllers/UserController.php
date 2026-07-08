<?php

namespace App\Http\Controllers;

use App\Auth\IdentityProvider;
use App\Services\UserService;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/UserController.php — User Proxy Controller
 *
 * SOLID Principles applied:
 *   - Single Responsibility: ONLY handles user-related proxy requests.
 *     Previously these methods were mixed with product and order logic
 *     in GatewayController.
 *   - Interface Segregation: Clients only depend on the methods they need.
 *     A developer working on users doesn't need to see order methods.
 *   - Dependency Inversion: Dependencies (UserService, IdentityProvider) are
 *     injected via constructor, not created statically.
 */

class UserController extends Controller
{
    private UserService $userService;
    private IdentityProvider $identityProvider;

    public function __construct(UserService $userService, IdentityProvider $identityProvider)
    {
        $this->userService = $userService;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/users — List all users.
     */
    public function index(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->getAll($headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * GET /api/users/{id} — Get a single user.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->getById($id, $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * POST /api/users — Create a new user.
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->create($request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * PUT /api/users/{id} — Update a user.
     */
    public function update(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->update($id, $request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * DELETE /api/users/{id} — Delete a user.
     */
    public function destroy(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userService->remove($id, $headers);

        return response()->json($result['body'], $result['status']);
    }
}
