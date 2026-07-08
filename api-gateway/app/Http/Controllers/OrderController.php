<?php

namespace App\Http\Controllers;

use App\Actions\Order\OrderAggregatedFetchAction;
use App\Actions\Order\OrderCreateAction;
use App\Actions\Order\OrderFetchAction;
use App\Actions\Order\OrderShowAction;
use App\Actions\Order\OrderUpdateStatusAction;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request, OrderFetchAction $action)
    {
        return $action($request);
    }

    public function show(Request $request, OrderShowAction $action, int $id)
    {
        return $action($request, $id);
    }

    public function store(Request $request, OrderCreateAction $action)
    {
        return $action($request);
    }

    public function updateStatus(Request $request, OrderUpdateStatusAction $action, int $id)
    {
        return $action($request, $id);
    }

    /**
     * GET /api/orders/aggregated — Orders enriched with product details.
     */
    public function aggregated(Request $request, OrderAggregatedFetchAction $action)
    {
        return $action($request);
    }
}
