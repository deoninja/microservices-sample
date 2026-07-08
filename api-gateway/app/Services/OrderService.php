<?php

namespace App\Services;

/*
 * app/Services/OrderService.php — Order Microservice Client
 *
 * Handles all communication with the Order microservice.
 * Follows SRP by focusing exclusively on order-related proxy calls.
 */

class OrderService extends BaseService
{
    /**
     * Get the Order Service base URL from config.
     */
    public function getBaseUrl(): string
    {
        return config('services.order_service.url', 'http://localhost:3003');
    }

    /**
     * Get all orders.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getAll(array $headers = []): array
    {
        return $this->get('/api/orders', $headers);
    }

    /**
     * Get a single order by ID.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getById(int $id, array $headers = []): array
    {
        return $this->get("/api/orders/{$id}", $headers);
    }

    /**
     * Create a new order.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function create(array $data, array $headers = []): array
    {
        return $this->post('/api/orders', $data, $headers);
    }

    /**
     * Update an order's status.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function updateStatus(int $id, array $data, array $headers = []): array
    {
        return $this->put("/api/orders/{$id}/status", $data, $headers);
    }
}
