<?php

namespace App\Contracts;

/*
 * app/Contracts/ServiceClientInterface.php — Microservice Proxy Contract
 *
 * This interface defines the contract that every microservice proxy client
 * must implement. It follows the Dependency Inversion Principle (DIP):
 * high-level modules (controllers) depend on this abstraction, not on
 * concrete implementations.
 *
 * By programming to an interface, we can:
 *   - Swap implementations without changing controllers (e.g. mock for tests)
 *   - Add new microservices by implementing this interface
 *   - Keep controllers decoupled from HTTP client details
 */

interface ServiceClientInterface
{
    /**
     * Get the base URL of the microservice.
     *
     * @return string e.g. "http://user-service:3001"
     */
    public function getBaseUrl(): string;

    /**
     * Send a GET request to the microservice.
     *
     * @param string       $path    Path relative to base URL (e.g. "/api/users")
     * @param array<string> $headers Optional extra headers
     * @return array{status: int, body: array<mixed>, success: bool}
     */
    public function get(string $path, array $headers = []): array;

    /**
     * Send a POST request to the microservice.
     *
     * @param string       $path    Path relative to base URL
     * @param array<mixed> $data    Request body data
     * @param array<string> $headers Optional extra headers
     * @return array{status: int, body: array<mixed>, success: bool}
     */
    public function post(string $path, array $data = [], array $headers = []): array;

    /**
     * Send a PUT request to the microservice.
     *
     * @param string       $path    Path relative to base URL
     * @param array<mixed> $data    Request body data
     * @param array<string> $headers Optional extra headers
     * @return array{status: int, body: array<mixed>, success: bool}
     */
    public function put(string $path, array $data = [], array $headers = []): array;

    /**
     * Send a DELETE request to the microservice.
     *
     * @param string       $path    Path relative to base URL
     * @param array<string> $headers Optional extra headers
     * @return array{status: int, body: array<mixed>, success: bool}
     */
    public function delete(string $path, array $headers = []): array;
}
