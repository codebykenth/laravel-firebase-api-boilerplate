<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Route not found exception
        $exceptions->renderable(function (RouteNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json([
                    'error' => 'Token not found',
                    'message' => $e->getMessage()
                ], 404);
            }
        });

        // Model not found exception
        $exceptions->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json(['message' => 'Resource not found'], 404);
            }
        });

        // Not found HTTP exception
        $exceptions->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json(['message' => 'Resource not found'], 404);
            }
        });

        // Validation exception
        $exceptions->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $e->validator->errors()->getMessages()
                ], 422);
            }
        });

        // Authentication exception
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => $e->getMessage()
                ], 401);
            }
        });

        // Authorization exception
        $exceptions->renderable(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => $e->getMessage()
                ], 403);
            }
        });

        // Method not allowed exception
        $exceptions->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json(['message' => 'Method not allowed'], 405);
            }
        });

        // Database query exception
        $exceptions->renderable(function (QueryException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }

                // Check if the error is a unique constraint violation
                if ($e->getCode() === '23000') { // SQLSTATE[23000]: Integrity constraint violation
                    return response()->json([
                        'error' => 'Duplicate Entry',
                        'message' => 'You have already submitted a request for this pet.'
                    ], 400);
                }

                // Don't expose query details in production
                $message = app()->environment('production') ? 'Database error' : $e->getMessage();
                // $message = $e->getMessage();

                return response()->json([
                    'error' => 'Database error',
                    'message' => $message,
                    'code' => $e->getCode()
                ], 500);
            }
        });

        // Throttle requests exception
        $exceptions->renderable(function (ThrottleRequestsException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                $retryAfter = $e->getHeaders()['Retry-After'] ?? null;
                return response()->json([
                    'error' => 'Too Many Requests',
                    'message' => 'API rate limit exceeded. Please slow down your requests.',
                    'retry_after' => $retryAfter ? (int) $retryAfter : 60
                ], 429);
            }
        });

        // General exception
        $exceptions->renderable(function (Exception $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if (DB::transactionLevel() > 0) {
                    DB::rollback();
                }
                return response()->json(['message' => $e->getMessage()], 500);
            }
        });
    })->create();
