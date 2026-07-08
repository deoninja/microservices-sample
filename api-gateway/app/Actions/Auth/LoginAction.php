<?php

namespace App\Actions\Auth;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginAction
{
    public function __construct(protected AuthService $authService) {}

    public function __invoke(string $username, string $password): JsonResponse
    {
        $result = $this->authService->login($username, $password);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status'] ?? 401);
        }

        return response()->json([
            'token' => $result['token'],
            'user'  => $result['user'],
        ]);
    }
}
