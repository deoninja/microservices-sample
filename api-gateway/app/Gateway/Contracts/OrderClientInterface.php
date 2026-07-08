<?php

namespace App\Gateway\Contracts;

use App\Gateway\DTOs\OrderData;

/*
 * app/Gateway/Contracts/OrderClientInterface.php — Port for Order Service
 */

interface OrderClientInterface
{
    /**
     * Get all orders.
     *
     * @return array{status: int, body: array<OrderData>, success: bool}
     */
    public function getAll(array $headers = []): array;

    /**
     * Get a single order by ID.
     *
     * @return array{status: int, body: OrderData|null, success: bool}
     */
    public function getById(int $id, array $headers = []): array;

    /**
     * Create a new order.
     *
     * @return array{status: int, body: OrderData, success: bool}
     */
    public function create(array $data, array $headers = []): array;

    /**
     * Update an order's status.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function updateStatus(int $id, array $data, array $headers = []): array;
}
