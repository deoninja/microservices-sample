<?php

namespace App\Actions\Order;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\OrderClientInterface;
use App\Gateway\Support\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderFetchAction
{
    public function __construct(
        protected OrderClientInterface $orderClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->getAll($headers);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        // Transform orders for the frontend using ResponseFormatter::map().
        // Demonstrates: field renaming, derived values, and data formatting.
        $body = ResponseFormatter::map($result['body'], function (array $order): array {
            return [
                'id'         => $order['id'],
                'customer'   => $order['customerName'],   // rename: customerName → customer
                'status'     => $order['status'],
                'items'      => $order['items'],
                'itemCount'  => count($order['items']),   // derived: compute from items array
                'total'      => $order['total'],
                // Note: createdAt is intentionally excluded — not needed by the frontend
            ];
        });

        return response()->json($body, $result['status']);
    }
}
