<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/*
 * app/Http/Controllers/Controller.php — Base Controller
 *
 * Shared helpers for all Presentation-layer controllers.
 * Follows Clean Architecture: this is part of the Presentation layer.
 */

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Serialize a DTO (or raw value) to a JSON-safe representation.
     *
     * If the value is an object with a toArray() method (like a DTO),
     * it converts it. Otherwise returns the value as-is (raw array, scalar, null).
     */
    protected function serialize(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        return $value;
    }

    /**
     * Serialize a collection of DTOs (or raw items) to a JSON-safe array.
     */
    protected function serializeCollection(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_map(fn ($item) => $this->serialize($item), $items);
    }
}
