<?php

namespace App\Actions\Product;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\ProductClientInterface;
use App\Gateway\Support\ResponseFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFetchAction
{
    public function __construct(
        protected ProductClientInterface $productClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $search  = $request->get('search', '');
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->getAll($search ?: null, $headers);

        if (!$result['success']) {
            return response()->json($result['body'], $result['status']);
        }

        // Format: strip internal fields before forwarding to frontend.
        // ResponseFormatter handles both single items and collections.
        $body = ResponseFormatter::except($result['body'], ['createdAt']);

        return response()->json($body, $result['status']);
    }
}
