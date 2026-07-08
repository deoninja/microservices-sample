<?php

namespace App\Http\Controllers;

use App\Auth\IdentityProvider;
use App\Services\OrderService;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/OrderController.php — Order Proxy Controller
 *
 * SOLID Principles applied:
 *   - Single Responsibility: ONLY handles order-related proxy requests.
 *   - Interface Segregation: Order-specific methods are isolated from
 *     unrelated user and product methods.
 *   - Dependency Inversion: Dependencies injected via constructor.
 */

class OrderController extends Controller
{
    private OrderService $orderService;
    private IdentityProvider $identityProvider;

    public function __construct(OrderService $orderService, IdentityProvider $identityProvider)
    {
        $this->orderService = $orderService;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/orders — List all orders.
     */
    public function index(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderService->getAll($headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * GET /api/orders/{id} — Get a single order.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderService->getById($id, $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * POST /api/orders — Place a new order.
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderService->create($request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * PUT /api/orders/{id}/status — Update an order's status.
     */
    public function updateStatus(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderService->updateStatus($id, $request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }
}
