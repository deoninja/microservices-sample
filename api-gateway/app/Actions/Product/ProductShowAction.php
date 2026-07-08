<?php

namespace App\Actions\Product;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\ProductClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductShowAction
{
    public function __construct(
        protected ProductClientInterface $productClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->getById($id, $headers);

        return response()->json($result['body'], $result['status']);
    }
}
