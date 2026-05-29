<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'Forbidden. You do not have permission to perform this action.'], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json(['message' => "{$model} not found."], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'The requested endpoint does not exist.'], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json(['message' => 'HTTP method not allowed for this endpoint.'], 405);
            }

            // Generic server error (don't expose details in production)
            $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = config('app.debug') ? $e->getMessage() : 'An internal server error occurred.';

            return response()->json(['message' => $message], $status);
        }

        return parent::render($request, $e);
    }
}
