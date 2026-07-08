<?php

namespace App\Http\Controllers;

use App\Actions\Product\ProductCreateAction;
use App\Actions\Product\ProductDeleteAction;
use App\Actions\Product\ProductFetchAction;
use App\Actions\Product\ProductShowAction;
use App\Actions\Product\ProductUpdateAction;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, ProductFetchAction $action)
    {
        return $action($request);
    }

    public function show(Request $request, ProductShowAction $action, int $id)
    {
        return $action($request, $id);
    }

    public function store(Request $request, ProductCreateAction $action)
    {
        return $action($request);
    }

    public function update(Request $request, ProductUpdateAction $action, int $id)
    {
        return $action($request, $id);
    }

    public function destroy(Request $request, ProductDeleteAction $action, int $id)
    {
        return $action($request, $id);
    }
}
