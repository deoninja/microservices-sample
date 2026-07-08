<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\MethodNotAllowedHttpException;
use Illuminate\Http\Exceptions\NotFoundHttpException;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        // 405 — wrong HTTP method (e.g. GET on a POST-only route)
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'error'   => 'Method not allowed',
                'message' => 'This endpoint does not support the ' . $request->method() . ' method.',
            ], 405);
        }

        // 404 — route does not exist
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'error'   => 'Not found',
                'message' => 'The requested endpoint does not exist.',
            ], 404);
        }

        // 422 — validation failed (Laravel throws this from $request->validate())
        if ($e instanceof ValidationException) {
            return response()->json([
                'error'   => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        }

        // 401 — authentication failed (invalid/expired token)
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'error'   => 'Unauthenticated',
                'message' => 'Authentication is required to access this resource.',
            ], 401);
        }

        // All other exceptions — return 500 with the message in debug mode
        return response()->json([
            'error'   => 'Server error',
            'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
        ], 500);
    }
}
