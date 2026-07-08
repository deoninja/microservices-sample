<?php

namespace App\Gateway\DTOs;

/*
 * app/Gateway/DTOs/UserData.php — User Data Transfer Object
 *
 * A typed, immutable object representing user data from the User Service.
 * Using DTOs instead of raw arrays ensures type safety and makes the
 * data contract explicit at every layer boundary.
 *
 * Immutability: All properties are readonly. To create a modified copy,
 * use the named constructor `fromArray()` or `with()` pattern.
 */

class UserData
{
    public readonly int $id;
    public readonly string $username;
    public readonly string $name;
    public readonly string $email;
    public readonly string $role;

    public function __construct(int $id, string $username, string $name, string $email, string $role = 'user')
    {
        $this->id       = $id;
        $this->username = $username;
        $this->name     = $name;
        $this->email    = $email;
        $this->role     = $role;
    }

    /**
     * Create a UserData instance from an associative array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id:       (int) ($data['id'] ?? 0),
            username: (string) ($data['username'] ?? ''),
            name:     (string) ($data['name'] ?? ''),
            email:    (string) ($data['email'] ?? ''),
            role:     (string) ($data['role'] ?? 'user'),
        );
    }

    /**
     * Convert back to an array for serialization.
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'username' => $this->username,
            'name'     => $this->name,
            'email'    => $this->email,
            'role'     => $this->role,
        ];
    }
}
