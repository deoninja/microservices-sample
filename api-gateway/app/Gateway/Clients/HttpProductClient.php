<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\ProductClientInterface;
use App\Gateway\DTOs\ProductData;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpProductClient.php — Product Service Http Adapter
 *
 * Concrete implementation of ProductClientInterface using Laravel's Http facade.
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

        return $this->parseResponse($response, true);
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

    private function parseResponse($response, bool $isCollection = false): array
    {
        $body = $response->json() ?? [];
        $success = $response->successful();

        if ($success) {
            if ($isCollection) {
                $body = array_map(fn ($item) => ProductData::fromArray($item), $body);
            } elseif (isset($body['id'])) {
                $body = ProductData::fromArray($body);
            }
        }

        return [
            'status'  => $response->status(),
            'body'    => $body,
            'success' => $success,
        ];
    }
}
