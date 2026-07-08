<?php

namespace App\Gateway\Contracts;

use App\Gateway\DTOs\ProductData;

/*
 * app/Gateway/Contracts/ProductClientInterface.php — Port for Product Service
 */

interface ProductClientInterface
{
    /**
     * Get all products, optionally filtered by search term.
     *
     * @return array{status: int, body: array<ProductData>, success: bool}
     */
    public function getAll(?string $search = null, array $headers = []): array;

    /**
     * Get a single product by ID.
     *
     * @return array{status: int, body: ProductData|null, success: bool}
     */
    public function getById(int $id, array $headers = []): array;

    /**
     * Create a new product.
     *
     * @return array{status: int, body: ProductData, success: bool}
     */
    public function create(array $data, array $headers = []): array;

    /**
     * Update a product.
     *
     * @return array{status: int, body: ProductData, success: bool}
     */
    public function update(int $id, array $data, array $headers = []): array;

    /**
     * Delete a product.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array;
}
