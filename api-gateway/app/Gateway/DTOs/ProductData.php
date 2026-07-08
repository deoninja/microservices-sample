<?php

namespace App\Gateway\DTOs;

/*
 * app/Gateway/DTOs/ProductData.php — Product Data Transfer Object
 *
 * Typed representation of product data from the Product Service.
 * Used at the boundary between the Infrastructure and Presentation layers.
 */

class ProductData implements \JsonSerializable
{
    public readonly int $id;
    public readonly string $name;
    public readonly float $price;
    public readonly string $description;
    public readonly int $stock;
    public readonly string $createdAt;

    public function __construct(int $id, string $name, float $price, string $description = '', int $stock = 0, string $createdAt = '')
    {
        $this->id          = $id;
        $this->name        = $name;
        $this->price       = $price;
        $this->description = $description;
        $this->stock       = $stock;
        $this->createdAt   = $createdAt;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id:          (int) ($data['id'] ?? 0),
            name:        (string) ($data['name'] ?? ''),
            price:       (float) ($data['price'] ?? 0.0),
            description: (string) ($data['description'] ?? ''),
            stock:       (int) ($data['stock'] ?? 0),
            createdAt:   (string) ($data['createdAt'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'price'       => $this->price,
            'description' => $this->description,
            'stock'       => $this->stock,
            'createdAt'  => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
