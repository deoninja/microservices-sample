<?php

namespace App\Services;

use App\Gateway\Contracts\UserClientInterface;
use App\Models\User;

/*
 * app/Services/AuthService.php — Authentication Orchestration
 *
 * Coordinates login/register across UserClient + Passport.
 * Receives raw arrays from the client (no DTO mapping) and
 * works directly with array access for local User records.
 */

class AuthService
{
    private UserClientInterface $userClient;

    public function __construct(UserClientInterface $userClient)
    {
        $this->userClient = $userClient;
    }

    /**
     * @return array{success: bool, token?: string, user?: array, status?: int, body?: array}
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

        $userData = $result['body'];  // raw array from microservice

        // Synchronize local user record for Passport
        $localUser = User::updateOrCreate(
            ['id' => $userData['id']],
            $userData
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
     * @return array{success: bool, status: int, body: array}
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

        $userData = $result['body'];  // raw array from microservice

        // Create local user record for Passport
        User::create($userData);

        return [
            'success' => true,
            'status'  => 201,
            'body'    => $userData,
        ];
    }
}
