<?php

namespace App\Actions\Order;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\OrderClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCreateAction
{
    public function __construct(
        protected OrderClientInterface $orderClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->create($request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }
}
