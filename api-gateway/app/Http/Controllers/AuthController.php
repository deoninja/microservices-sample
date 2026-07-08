<?php

namespace App\Http\Controllers;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterAction;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action)
    {
        return $action(
            $request->input('username'),
            $request->input('password')
        );
    }

    public function register(RegisterRequest $request, RegisterAction $action)
    {
        return $action(
            $request->input('username'),
            $request->input('password'),
            $request->input('name'),
            $request->input('email')
        );
    }
}
