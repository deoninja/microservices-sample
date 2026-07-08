<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\OrderClientInterface;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpOrderClient.php — Order Service Http Adapter
 *
 * Forwards raw microservice responses. Use ResponseFormatter in Actions
 * to filter fields before forwarding to frontend.
 */

class HttpOrderClient implements OrderClientInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.order_service.url', 'http://localhost:3003');
    }

    public function getAll(array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/orders");

        return $this->parseResponse($response);
    }

    public function getById(int $id, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get("{$this->baseUrl}/api/orders/{$id}");

        return $this->parseResponse($response);
    }

    public function create(array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->post("{$this->baseUrl}/api/orders", $data);

        return $this->parseResponse($response);
    }

    public function updateStatus(int $id, array $data, array $headers = []): array
    {
        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->put("{$this->baseUrl}/api/orders/{$id}/status", $data);

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
