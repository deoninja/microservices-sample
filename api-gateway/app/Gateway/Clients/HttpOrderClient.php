<?php

namespace App\Gateway\Clients;

use App\Gateway\Contracts\OrderClientInterface;
use App\Gateway\DTOs\OrderData;
use Illuminate\Support\Facades\Http;

/*
 * app/Gateway/Clients/HttpOrderClient.php — Order Service Http Adapter
 *
 * Concrete implementation of OrderClientInterface using Laravel's Http facade.
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

        return $this->parseResponse($response, true);
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

    private function parseResponse($response, bool $isCollection = false): array
    {
        $body = $response->json() ?? [];
        $success = $response->successful();

        if ($success) {
            if ($isCollection) {
                $body = array_map(fn ($item) => OrderData::fromArray($item), $body);
            } elseif (isset($body['id'])) {
                $body = OrderData::fromArray($body);
            }
        }

        return [
            'status'  => $response->status(),
            'body'    => $body,
            'success' => $success,
        ];
    }
}
