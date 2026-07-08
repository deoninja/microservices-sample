<?php

namespace App\Services;

use App\Contracts\ServiceClientInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/*
 * app/Services/BaseService.php — Abstract Microservice Client
 *
 * This abstract class implements the common HTTP request logic shared
 * by all microservice proxy clients. Concrete services only need to
 * provide their base URL.
 *
 * SOLID Principles:
 *   - Single Responsibility: Only handles HTTP communication with one microservice
 *   - Open/Closed: New microservices extend this class without modifying it
 *   - Dependency Inversion: Implements ServiceClientInterface, depends on Guzzle via
 *     constructor injection rather than creating its own client statically
 */

abstract class BaseService implements ServiceClientInterface
{
    /**
     * The Guzzle HTTP client instance.
     */
    protected Client $client;

    /**
     * Create a new service client.
     *
     * Guzzle is injected so we can swap it for a mock in tests (DIP).
     * If no client is provided, a default one is created.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout'         => 10,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ]);
    }

    /**
     * Get the base URL of the microservice.
     * Each concrete service must provide its own URL.
     */
    abstract public function getBaseUrl(): string;

    /**
     * Send a GET request.
     */
    public function get(string $path, array $headers = []): array
    {
        return $this->request('GET', $path, [], $headers);
    }

    /**
     * Send a POST request.
     */
    public function post(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $path, $data, $headers);
    }

    /**
     * Send a PUT request.
     */
    public function put(string $path, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $path, $data, $headers);
    }

    /**
     * Send a DELETE request.
     */
    public function delete(string $path, array $headers = []): array
    {
        return $this->request('DELETE', $path, [], $headers);
    }

    /**
     * Execute an HTTP request against the microservice.
     *
     * Protected so child classes can call it directly to avoid name
     * collisions with service-specific methods (e.g. a service with
     * a delete(int $id) method that differs from delete(string $path)).
     */
    protected function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');

            $options = [
                'headers' => array_merge([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ], $headers),
            ];

            if (!empty($data) && strtoupper($method) !== 'GET') {
                $options['json'] = $data;
            }

            $response = $this->client->request(strtoupper($method), $url, $options);
            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            return [
                'status'  => $response->getStatusCode(),
                'body'    => $body,
                'success' => $response->getStatusCode() < 400,
            ];
        } catch (GuzzleException $e) {
            Log::error('Microservice request failed: ' . $e->getMessage(), [
                'method' => $method,
                'url'    => $url ?? $path,
                'service'=> static::class,
            ]);

            return [
                'status'  => 502,
                'body'    => [
                    'error'   => 'Microservice unavailable',
                    'message' => $e->getMessage(),
                ],
                'success' => false,
            ];
        }
    }
}
