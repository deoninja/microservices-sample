<?php

namespace App\Gateway\DTOs;

/*
 * app/Gateway/DTOs/OrderData.php — Order Data Transfer Object
 *
 * Typed representation of order data from the Order Service.
 */

class OrderData implements \JsonSerializable
{
    public readonly int $id;
    public readonly int $userId;
    public readonly string $customerName;
    public readonly array $items;
    public readonly float $total;
    public readonly string $status;
    public readonly string $createdAt;

    public function __construct(
        int $id,
        int $userId,
        string $customerName,
        array $items,
        float $total,
        string $status = 'pending',
        string $createdAt = ''
    ) {
        $this->id           = $id;
        $this->userId       = $userId;
        $this->customerName = $customerName;
        $this->items        = $items;
        $this->total        = $total;
        $this->status       = $status;
        $this->createdAt    = $createdAt;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:           (int) ($data['id'] ?? 0),
            userId:       (int) ($data['userId'] ?? 0),
            customerName: (string) ($data['customerName'] ?? ''),
            items:        (array) ($data['items'] ?? []),
            total:        (float) ($data['total'] ?? 0.0),
            status:       (string) ($data['status'] ?? 'pending'),
            createdAt:    (string) ($data['createdAt'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'userId'       => $this->userId,
            'customerName' => $this->customerName,
            'items'        => $this->items,
            'total'        => $this->total,
            'status'       => $this->status,
            'createdAt'    => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
