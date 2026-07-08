<?php

namespace App\Gateway\Contracts;

use App\Gateway\DTOs\UserData;

/*
 * app/Gateway/Contracts/UserClientInterface.php — Port for User Service
 *
 * Defines the contract for communicating with the User microservice.
 * Following Clean Architecture, this is a PORT (interface) in the
 * Infrastructure layer. Concrete adapters (HttpUserClient) implement it.
 *
 * The controllers depend on this interface, not on concrete implementations
 * (Dependency Inversion Principle).
 */

interface UserClientInterface
{
    /**
     * Authenticate a user and return their data.
     *
     * @return array{status: int, body: UserData|null, success: bool}
     */
    public function login(string $username, string $password): array;

    /**
     * Register a new user and return their data.
     *
     * @return array{status: int, body: UserData|null, success: bool}
     */
    public function register(string $username, string $password, string $name, string $email): array;

    /**
     * Get all users.
     *
     * @return array{status: int, body: array<UserData>, success: bool}
     */
    public function getAll(array $headers = []): array;

    /**
     * Get a single user by ID.
     *
     * @return array{status: int, body: UserData|null, success: bool}
     */
    public function getById(int $id, array $headers = []): array;

    /**
     * Create a new user.
     *
     * @return array{status: int, body: UserData, success: bool}
     */
    public function create(array $data, array $headers = []): array;

    /**
     * Update a user.
     *
     * @return array{status: int, body: UserData, success: bool}
     */
    public function update(int $id, array $data, array $headers = []): array;

    /**
     * Delete a user.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array;
}
