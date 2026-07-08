<?php

namespace App\Http\Controllers;

use App\Actions\User\UserCreateAction;
use App\Actions\User\UserDeleteAction;
use App\Actions\User\UserFetchAction;
use App\Actions\User\UserShowAction;
use App\Actions\User\UserUpdateAction;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request, UserFetchAction $action)
    {
        return $action($request);
    }

    public function show(Request $request, UserShowAction $action, int $id)
    {
        return $action($request, $id);
    }

    public function store(Request $request, UserCreateAction $action)
    {
        return $action($request);
    }

    public function update(Request $request, UserUpdateAction $action, int $id)
    {
        return $action($request, $id);
    }

    public function destroy(Request $request, UserDeleteAction $action, int $id)
    {
        return $action($request, $id);
    }
}
