<?php

namespace App\Gateway\Contracts;

/*
 * app/Gateway/Contracts/ProductClientInterface.php — Port for Product Service
 */

interface ProductClientInterface
{
    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function getAll(?string $search = null, array $headers = []): array;

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
    public function update(int $id, array $data, array $headers = []): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array;
}
