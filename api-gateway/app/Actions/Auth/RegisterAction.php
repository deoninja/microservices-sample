<?php

namespace App\Actions\Auth;

use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterAction
{
    public function __construct(protected AuthService $authService) {}

    public function __invoke(string $username, string $password, string $name, string $email): JsonResponse
    {
        $result = $this->authService->register($username, $password, $name, $email);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body'], 201);
    }
}
