<?php

namespace App\Services;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\OrderClientInterface;
use App\Gateway\Contracts\ProductClientInterface;
use App\Gateway\Support\ResponseFormatter;
use Illuminate\Http\Request;

/*
 * app/Services/OrderAggregationService.php — Order + Product Aggregation
 *
 * Application/Orchestration layer service that combines data from two
 * microservices into a single enriched response.
 *
 * Flow:
 *   1. Fetch all orders (1 HTTP call)
 *   2. Fetch all products (1 HTTP call — avoids N+1 per order item)
 *   3. Build a productId → product lookup map
 *   4. Enrich each order item with product details (stock, description)
 *   5. Transform with ResponseFormatter::map()
 *
 * Only 2 HTTP calls total regardless of how many orders or items exist.
 */

class OrderAggregationService
{
    public function __construct(
        protected OrderClientInterface $orderClient,
        protected ProductClientInterface $productClient,
        protected IdentityProvider $identityProvider,
    ) {}

    /**
     * Fetch orders enriched with product details for each item.
     *
     * @return array{status: int, body: array, success: bool}
     */
    public function getAggregatedOrders(Request $request): array
    {
        $headers = $this->identityProvider->getHeaders($request);

        // 1. Fetch orders
        $ordersResult = $this->orderClient->getAll($headers);
        if (!$ordersResult['success']) {
            return $ordersResult;
        }

        // 2. Fetch all products
        $productsResult = $this->productClient->getAll();

        // 3. Build product lookup map (empty if fetch failed or body is not a collection)
        $productMap = [];
        if ($productsResult['success'] && is_array($productsResult['body'])) {
            foreach ($productsResult['body'] as $product) {
                if (isset($product['id'])) {
                    $productMap[$product['id']] = $product;
                }
            }
        }

        // 4. Enrich orders using ResponseFormatter::map()
        $body = ResponseFormatter::map($ordersResult['body'], function (array $order) use ($productMap): array {
            $enrichedItems = array_map(function (array $item) use ($productMap): array {
                $product = $productMap[$item['productId']] ?? null;

                return [
                    'productId'   => $item['productId'],
                    'name'        => $item['name'],
                    'quantity'    => $item['quantity'],
                    'price'       => $item['price'],
                    'subtotal'    => round($item['quantity'] * $item['price'], 2),
                    'inStock'     => $product ? $product['stock'] : null,
                    'description' => $product ? $product['description'] : null,
                ];
            }, $order['items']);

            return [
                'id'        => $order['id'],
                'customer'  => $order['customerName'],
                'status'    => $order['status'],
                'itemCount' => count($order['items']),
                'items'     => $enrichedItems,
                'total'     => $order['total'],
            ];
        });

        return [
            'status'  => $ordersResult['status'],
            'body'    => $body,
            'success' => true,
        ];
    }
}
