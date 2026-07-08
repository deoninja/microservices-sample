<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\UserClientInterface;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpUserClient.php — User Service Http Adapter
 *
 * Forwards raw microservice responses. No DTO mapping — the response
 * is passed through as-is. Use ResponseFormatter in the Action layer
 * when filtering/transformation is needed.
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

        return [
            'status'  => $response->status(),
            'body'    => $body,
            'success' => $response->successful(),
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

        return [
            'status'  => $response->status(),
            'body'    => $body,
            'success' => $response->successful(),
        ];
    }

    public function getAll(array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/users");

        return $this->parseResponse($response);
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

    /**
     * Return the raw decoded JSON body — no DTO mapping.
     */
    private function parseResponse($response): array
    {
        return [
            'status'  => $response->status(),
            'body'    => $response->json() ?? [],
            'success' => $response->successful(),
        ];
    }
}
