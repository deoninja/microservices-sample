<?php

namespace App\Gateway\Contracts;

/*
 * app/Gateway/Contracts/OrderClientInterface.php — Port for Order Service
 */

interface OrderClientInterface
{
    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function getAll(array $headers = []): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function getById(int $id, array $headers = []): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function create(array $data, array $headers = []): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function updateStatus(int $id, array $data, array $headers = []): array;
}
