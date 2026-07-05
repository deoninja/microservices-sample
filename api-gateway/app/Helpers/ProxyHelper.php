<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/*
 * app/Helpers/ProxyHelper.php — HTTP Request Forwarder
 *
 * This is the class that physically sends HTTP requests from the gateway
 * to the microservices using Guzzle (a PHP HTTP client library).
 *
 * It is used by both AuthController and GatewayController.
 * Every call to a microservice goes through this class.
 *
 * Two public methods:
 *   forward()    — sends an HTTP request and returns a normalised result array
 *   serviceUrl() — looks up a microservice's base URL from config
 */

class ProxyHelper
{
    /*
     * getClient() — Create and configure a Guzzle HTTP client.
     *
     * A new client instance is created on each call (stateless).
     * The options set here apply to every request made with this client.
     */
    private static function getClient(): Client
    {
        return new Client([
            // Maximum seconds to wait for the microservice to send a complete response.
            // If the service takes longer than 10s, Guzzle throws a ConnectException.
            'timeout' => 10,

            // Maximum seconds to wait while establishing the TCP connection.
            // If the service isn't reachable within 5s, fail fast rather than hanging.
            'connect_timeout' => 5,

            // By default Guzzle throws exceptions for 4xx and 5xx responses.
            // Setting this to false means we handle those status codes ourselves
            // instead of catching exceptions for every non-200 response.
            'http_errors' => false,
        ]);
    }

    /*
     * forward() — Send an HTTP request to a microservice and return the result.
     *
     * @param string $method   HTTP verb: 'GET', 'POST', 'PUT', 'DELETE'
     * @param string $url      Full URL of the microservice endpoint
     * @param array  $data     Request body data (only sent for non-GET requests)
     * @param array  $headers  Extra headers to include (e.g. X-User-Id, X-User-Role)
     *
     * @return array {
     *   'status'  => int,   // HTTP status code from the microservice (200, 404, 502, etc.)
     *   'body'    => array, // Decoded JSON response body
     *   'success' => bool,  // true if status < 400, false otherwise
     * }
     */
    public static function forward(string $method, string $url, array $data = [], array $headers = []): array
    {
        try {
            // Build the request options array.
            // array_merge puts our default headers first, then the caller's headers on top.
            // If the caller passes X-User-Id, it will be included alongside Accept/Content-Type.
            $options = [
                'headers' => array_merge([
                    // Tell the microservice we expect a JSON response.
                    'Accept'       => 'application/json',
                    // Tell the microservice we're sending JSON in the body.
                    'Content-Type' => 'application/json',
                ], $headers), // merge in X-User-Id, X-User-Role etc. from the controller
            ];

            // Only attach a request body for non-GET requests that have data.
            // GET requests don't have a body — parameters go in the URL query string instead.
            // The 'json' key tells Guzzle to JSON-encode $data and set Content-Type automatically.
            if (!empty($data) && strtoupper($method) !== 'GET') {
                $options['json'] = $data;
            }

            // Send the request. strtoupper() ensures the method is always uppercase
            // (Guzzle requires 'GET' not 'get').
            $response = self::getClient()->request(strtoupper($method), $url, $options);

            // Read the response body stream and decode it from JSON to a PHP array.
            // The ?? [] fallback handles the rare case where the body is empty or not valid JSON.
            $body = json_decode($response->getBody()->getContents(), true) ?? [];

            return [
                'status'  => $response->getStatusCode(),
                'body'    => $body,
                // Treat any status below 400 as success (200 OK, 201 Created, 204 No Content, etc.)
                'success' => $response->getStatusCode() < 400,
            ];

        } catch (GuzzleException $e) {
            // A GuzzleException means we couldn't reach the microservice at all.
            // This happens when:
            //   - The service is not running (connection refused)
            //   - The service took too long to respond (timeout)
            //   - There's a network issue between containers
            //
            // Log the full error for debugging (visible in storage/logs/laravel.log).
            Log::error('Proxy request failed: ' . $e->getMessage(), [
                'method' => $method,
                'url'    => $url,
            ]);

            // Return a 502 Bad Gateway response — the standard HTTP status for
            // "I'm a gateway and the upstream server I depend on failed."
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

    /*
     * serviceUrl() — Look up a microservice's base URL from config.
     *
     * Reads from config/services.php which gets its values from .env.
     *
     * Examples:
     *   serviceUrl('user_service')    → 'http://localhost:3001'  (local)
     *                                 → 'http://user-service:3001' (Docker)
     *   serviceUrl('product_service') → 'http://localhost:3002'
     *   serviceUrl('order_service')   → 'http://localhost:3003'
     *
     * The dot notation 'services.user_service.url' maps to:
     *   config/services.php → ['user_service' => ['url' => '...']]
     */
    public static function serviceUrl(string $service): string
    {
        return config("services.{$service}.url", 'http://localhost');
    }
}
