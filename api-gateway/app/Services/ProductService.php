<?php

namespace App\Services;

/*
 * app/Services/ProductService.php — Product Microservice Client
 *
 * This service handles ALL communication with the Product microservice.
 * It follows the Single Responsibility Principle by focusing only on
 * product-related proxy calls.
 *
 * The getBaseUrl() method reads from config so the URL can be changed
 * per environment (local vs Docker) without modifying code (OCP).
 */

class ProductService extends BaseService
{
    /**
     * Get the Product Service base URL from config.
     */
    public function getBaseUrl(): string
    {
        return config('services.product_service.url', 'http://localhost:3002');
    }

    /**
     * Get all products, optionally filtered by search term.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getAll(?string $search = null, array $headers = []): array
    {
        $path = '/api/products';

        if ($search) {
            $path .= '?search=' . urlencode($search);
        }

        return $this->get($path, $headers);
    }

    /**
     * Get a single product by ID.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getById(int $id, array $headers = []): array
    {
        return $this->get("/api/products/{$id}", $headers);
    }

    /**
     * Create a new product.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function create(array $data, array $headers = []): array
    {
        return $this->post('/api/products', $data, $headers);
    }

    /**
     * Update an existing product.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function update(int $id, array $data, array $headers = []): array
    {
        return $this->put("/api/products/{$id}", $data, $headers);
    }

    /**
     * Delete a product.
     *
     * Named 'remove' instead of 'delete' to avoid a signature collision
     * with BaseService::delete(string $path).
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array
    {
        return $this->request('DELETE', "/api/products/{$id}", [], $headers);
    }
}
