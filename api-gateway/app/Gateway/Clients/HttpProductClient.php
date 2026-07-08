<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\ProductClientInterface;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpProductClient.php — Product Service Http Adapter
 *
 * Forwards raw microservice responses. Use ResponseFormatter in Actions
 * to filter fields (e.g. strip createdAt) before forwarding to frontend.
 */

class HttpProductClient implements ProductClientInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.product_service.url', 'http://localhost:3002');
    }

    public function getAll(?string $search = null, array $headers = []): array
    {
        $path = "{$this->baseUrl}/api/products";

        if ($search) {
            $path .= '?search=' . urlencode($search);
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get($path);

        return $this->parseResponse($response);
    }

    public function getById(int $id, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/products/{$id}");

        return $this->parseResponse($response);
    }

    public function create(array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post("{$this->baseUrl}/api/products", $data);

        return $this->parseResponse($response);
    }

    public function update(int $id, array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->put("{$this->baseUrl}/api/products/{$id}", $data);

        return $this->parseResponse($response);
    }

    public function remove(int $id, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->delete("{$this->baseUrl}/api/products/{$id}");

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
