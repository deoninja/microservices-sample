<?php

namespace App\Gateway\Support;

/*
 * app/Gateway/Support/ResponseFormatter.php — Raw Response Forwarder
 *
 * By default, the gateway forwards the raw microservice response as-is.
 * Use the formatting methods here to optionally strip or transform fields
 * before sending them to the frontend.
 *
 * Why raw by default?
 *   For a proxy gateway, the default behavior should be pass-through.
 *   Mapping every response to a DTO just to map it back to JSON is
 *   unnecessary overhead. DTOs are still available for explicit typed
 *   validation when needed — this is just the default path.
 *
 * Methods:
 *   raw()     — passthrough, no transformation
 *   except()  — remove specified keys
 *   only()    — keep only specified keys
 *   map()     — custom callback per item
 *
 * All methods handle both single items (associative arrays) and
 * collections (indexed arrays of associative arrays) transparently.
 */

class ResponseFormatter
{
    /**
     * Forward the raw response body as-is.
     */
    public static function raw(array $body): array
    {
        return $body;
    }

    /**
     * Remove specified keys from the response.
     *
     * @param  array         $body  Single item or collection
     * @param  string|array  $keys  Key(s) to remove
     * @return array
     */
    public static function except(array $body, string|array $keys): array
    {
        $keys = (array) $keys;

        if (self::isCollection($body)) {
            return array_map(fn ($item) => array_diff_key($item, array_flip($keys)), $body);
        }

        return array_diff_key($body, array_flip($keys));
    }

    /**
     * Keep only specified keys from the response.
     *
     * @param  array         $body  Single item or collection
     * @param  string|array  $keys  Key(s) to keep
     * @return array
     */
    public static function only(array $body, string|array $keys): array
    {
        $keys = (array) $keys;

        if (self::isCollection($body)) {
            return array_map(fn ($item) => array_intersect_key($item, array_flip($keys)), $body);
        }

        return array_intersect_key($body, array_flip($keys));
    }

    /**
     * Apply a custom callback to transform the response.
     *
     * For collections, the callback receives each item.
     * For single items, the callback receives the whole body.
     */
    public static function map(array $body, callable $callback): array
    {
        if (self::isCollection($body)) {
            return array_map($callback, $body);
        }

        return $callback($body);
    }

    /**
     * Detect whether the body is a collection (indexed array) or a single item.
     */
    private static function isCollection(array $body): bool
    {
        if (empty($body)) {
            return false;
        }
        return array_keys($body) === range(0, count($body) - 1);
    }
}
