<?php

namespace App\Http\Controllers;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\ProductClientInterface;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/ProductController.php — Product Endpoint (Presentation Layer)
 *
 * Clean Architecture:
 *   Presentation Layer — Thin entry point.
 */

class ProductController extends Controller
{
    private ProductClientInterface $productClient;
    private IdentityProvider $identityProvider;

    public function __construct(ProductClientInterface $productClient, IdentityProvider $identityProvider)
    {
        $this->productClient = $productClient;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/products — List all products with optional search.
     */
    public function index(Request $request)
    {
        $search  = $request->get('search', '');
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->getAll($search ?: null, $headers);

        return response()->json(
            $this->serializeCollection($result['body']),
            $result['status']
        );
    }

    /**
     * GET /api/products/{id} — Get a single product.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->getById($id, $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * POST /api/products — Create a new product (requires auth).
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->create($request->all(), $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * PUT /api/products/{id} — Update a product (requires auth).
     */
    public function update(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->update($id, $request->all(), $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * DELETE /api/products/{id} — Delete a product (requires auth).
     */
    public function destroy(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productClient->remove($id, $headers);

        return response()->json($result['body'], $result['status']);
    }

}

