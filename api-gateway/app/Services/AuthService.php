<?php

namespace App\Services;

use App\Gateway\Contracts\UserClientInterface;
use App\Gateway\DTOs\UserData;
use App\Models\User;

/*
 * app/Services/AuthService.php — Authentication Orchestration
 *
 * This service sits in the Application/Orchestration layer.
 * It coordinates the login/register flow across multiple actors:
 *   1. UserClientInterface (Infrastructure) — communicates with User Service
 *   2. User model (infrastructure) — creates/updates local Passport records
 *   3. Passport — issues personal access tokens
 *
 * The controllers depend on this service, keeping them thin.
 * This service depends on abstractions (interfaces), not concretions.
 */

class AuthService
{
    private UserClientInterface $userClient;

    public function __construct(UserClientInterface $userClient)
    {
        $this->userClient = $userClient;
    }

    /**
     * Authenticate a user and issue a Passport token.
     *
     * @return array{success: bool, token?: string, user?: UserData, status?: int, body?: array}
     */
    public function login(string $username, string $password): array
    {
        $result = $this->userClient->login($username, $password);

        if (!$result['success']) {
            return [
                'success' => false,
                'status'  => 401,
                'body'    => ['error' => 'Invalid credentials'],
            ];
        }

        /** @var UserData $userData */
        $userData = $result['body'];

        // Synchronize local user record for Passport
        $localUser = User::updateOrCreate(
            ['id' => $userData->id],
            $userData->toArray()
        );

        // Issue a Passport personal access token
        $token = $localUser->createToken('api-access-token')->accessToken;

        return [
            'success' => true,
            'token'   => $token,
            'user'    => $userData,
        ];
    }

    /**
     * Register a new user.
     *
     * @return array{success: bool, status: int, body: UserData|array}
     */
    public function register(string $username, string $password, string $name, string $email): array
    {
        $result = $this->userClient->register($username, $password, $name, $email);

        if (!$result['success']) {
            return [
                'success' => false,
                'status'  => $result['status'],
                'body'    => $result['body'],
            ];
        }

        /** @var UserData $userData */
        $userData = $result['body'];

        // Create local user record for Passport
        User::create($userData->toArray());

        return [
            'success' => true,
            'status'  => 201,
            'body'    => $userData,
        ];
    }
}
