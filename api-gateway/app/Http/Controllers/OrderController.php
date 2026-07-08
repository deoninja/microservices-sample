<?php

namespace App\Http\Controllers;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\OrderClientInterface;
use Illuminate\Http\Request;

/*
 * app/Http/Controllers/OrderController.php — Order Endpoint (Presentation Layer)
 *
 * Clean Architecture:
 *   Presentation Layer — Thin entry point.
 */

class OrderController extends Controller
{
    private OrderClientInterface $orderClient;
    private IdentityProvider $identityProvider;

    public function __construct(OrderClientInterface $orderClient, IdentityProvider $identityProvider)
    {
        $this->orderClient = $orderClient;
        $this->identityProvider = $identityProvider;
    }

    /**
     * GET /api/orders — List all orders.
     */
    public function index(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->getAll($headers);

        return response()->json(
            $this->serializeCollection($result['body']),
            $result['status']
        );
    }

    /**
     * GET /api/orders/{id} — Get a single order.
     */
    public function show(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->getById($id, $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * POST /api/orders — Place a new order.
     */
    public function store(Request $request)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->create($request->all(), $headers);

        return response()->json(
            $this->serialize($result['body']),
            $result['status']
        );
    }

    /**
     * PUT /api/orders/{id}/status — Update an order's status.
     */
    public function updateStatus(Request $request, int $id)
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->orderClient->updateStatus($id, $request->all(), $headers);

        return response()->json($result['body'], $result['status']);
    }

}

