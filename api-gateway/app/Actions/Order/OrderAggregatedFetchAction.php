<?php

namespace App\Actions\Order;

use App\Services\OrderAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderAggregatedFetchAction
{
    public function __construct(protected OrderAggregationService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->service->getAggregatedOrders($request);

        return response()->json($result['body'], $result['status']);
    }
}
