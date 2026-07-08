<?php

namespace App\Services;

/*
 * app/Services/UserService.php — User Microservice Client
 *
 * This service is responsible ONLY for communicating with the User
 * microservice (Single Responsibility Principle). It knows the base
 * URL and exposes methods that map to the User Service API endpoints.
 *
 * Controllers that need user data depend on this service via the
 * ServiceClientInterface contract (Dependency Inversion Principle),
 * not on static helper methods.
 */

class UserService extends BaseService
{
    /**
     * Get the User Service base URL from config.
     */
    public function getBaseUrl(): string
    {
        return config('services.user_service.url', 'http://localhost:3001');
    }

    /**
     * Forward login credentials to the User Service.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function login(array $credentials): array
    {
        return $this->post('/api/users/login', $credentials);
    }

    /**
     * Forward registration data to the User Service.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function register(array $userData): array
    {
        return $this->post('/api/users/register', $userData);
    }

    /**
     * Get all users.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getAll(array $headers = []): array
    {
        return $this->get('/api/users', $headers);
    }

    /**
     * Get a single user by ID.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getById(int $id, array $headers = []): array
    {
        return $this->get("/api/users/{$id}", $headers);
    }

    /**
     * Create a new user.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function create(array $data, array $headers = []): array
    {
        return $this->post('/api/users', $data, $headers);
    }

    /**
     * Update an existing user.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function update(int $id, array $data, array $headers = []): array
    {
        return $this->put("/api/users/{$id}", $data, $headers);
    }

    /**
     * Delete a user.
     *
     * Named 'remove' instead of 'delete' to avoid a signature collision
     * with BaseService::delete(string $path). PHP does not allow a child
     * class to have a method with the same name but different parameter
     * types (int $id vs string $path).
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array
    {
        return $this->request('DELETE', "/api/users/{$id}", [], $headers);
    }
}
