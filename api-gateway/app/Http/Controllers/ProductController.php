<?php

namespace App\Http\Controllers;

use App\Auth\IdentityProvider;
use App\Services\ProductService;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/ProductController.php — Product Proxy Controller
 *
 * SOLID Principles applied:
 *   - Single Responsibility: ONLY handles product-related proxy requests.
 *   - Interface Segregation: Product-specific methods are isolated from
 *     unrelated user and order methods.
 *   - Dependency Inversion: Dependencies injected via constructor.
 */

class ProductController extends Controller
{
    private ProductService $productService;
    private IdentityProvider $identityProvider;

    public function __construct(ProductService $productService, IdentityProvider $identityProvider)
    {
        $this->productService = $productService;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/products — List all products with optional search.
     */
    public function index(Request $request)
    {
        $search  = $request->get('search', '');
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productService->getAll($search ?: null, $headers);

        $products = collect($result['body'])->map(function ($product) {
            unset($product['createdAt']);
            return $product;
        });

        return response()->json($products, $result['status']);
    }

    /**
     * GET /api/products/{id} — Get a single product.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productService->getById($id, $headers);

        $productArray = (array) $result['body'];
        unset($productArray['createdAt']);

        return response()->json($productArray, $result['status']);
    }

    /**
     * POST /api/products — Create a new product (requires auth).
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productService->create($request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * PUT /api/products/{id} — Update a product (requires auth).
     */
    public function update(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productService->update($id, $request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * DELETE /api/products/{id} — Delete a product (requires auth).
     */
    public function destroy(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->productService->remove($id, $headers);

        return response()->json($result['body'], $result['status']);
    }
}
