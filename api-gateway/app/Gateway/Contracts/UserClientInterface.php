<?php

namespace App\Gateway\Contracts;

/*
 * app/Gateway/Contracts/UserClientInterface.php — Port for User Service
 *
 * Defines the contract for communicating with the User microservice.
 * All responses return raw decoded JSON arrays — no DTO mapping.
 * Use ResponseFormatter in Actions for optional field filtering.
 */

interface UserClientInterface
{
    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function login(string $username, string $password): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function register(string $username, string $password, string $name, string $email): array;

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
    public function update(int $id, array $data, array $headers = []): array;

    /**
     * @return array{status: int, body: array, success: bool}
     */
    public function remove(int $id, array $headers = []): array;
}
