<?php

namespace App\Actions\Order;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\OrderClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderUpdateStatusAction
{
    public function __construct(
        protected OrderClientInterface $orderClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->updateStatus($id, $request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }
}
