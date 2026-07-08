<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\UserClientInterface;
use App\Gateway\DTOs\UserData;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpUserClient.php — User Service Http Adapter
 *
 * Concrete implementation of UserClientInterface using Laravel's
 * Http facade. This is an Adapter in Clean Architecture terms —
 * it translates the application's internal calls into HTTP requests
 * to the User microservice.
 */

class HttpUserClient implements UserClientInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.user_service.url', 'http://localhost:3001');
    }

    public function login(string $username, string $password): array
    {
        $response = Http::timeout(10)
            ->post("{$this->baseUrl}/api/users/login", [
                'username' => $username,
                'password' => $password,
            ]);

        $body = $response->json() ?? [];
        $success = $response->successful();

        return [
            'status'  => $response->status(),
            'body'    => $success ? UserData::fromArray($body) : $body,
            'success' => $success,
        ];
    }

    public function register(string $username, string $password, string $name, string $email): array
    {
        $response = Http::timeout(10)
            ->post("{$this->baseUrl}/api/users/register", [
                'username' => $username,
                'password' => $password,
                'name'     => $name,
                'email'    => $email,
            ]);

        $body = $response->json() ?? [];
        $success = $response->successful();

        return [
            'status'  => $response->status(),
            'body'    => $success ? UserData::fromArray($body) : $body,
            'success' => $success,
        ];
    }

    public function getAll(array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/users");

        return $this->parseResponse($response, true);
    }

    public function getById(int $id, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/users/{$id}");

        return $this->parseResponse($response);
    }

    public function create(array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post("{$this->baseUrl}/api/users", $data);

        return $this->parseResponse($response);
    }

    public function update(int $id, array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->put("{$this->baseUrl}/api/users/{$id}", $data);

        return $this->parseResponse($response);
    }

    public function remove(int $id, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->delete("{$this->baseUrl}/api/users/{$id}");

        return $this->parseResponse($response);
    }

    private function parseResponse($response, bool $isCollection = false): array
    {
        $body = $response->json() ?? [];
        $success = $response->successful();

        if ($success) {
            if ($isCollection) {
                $body = array_map(fn ($item) => UserData::fromArray($item), $body);
            } elseif (isset($body['id'])) {
                $body = UserData::fromArray($body);
            }
        }

        return [
            'status'  => $response->status(),
            'body'    => $body,
            'success' => $success,
        ];
    }
}
