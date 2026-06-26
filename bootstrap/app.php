<?php

use App\Http\Middleware\ActiveUser;
use App\Http\Middleware\CheckTokenVersion;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(
            ForceJsonResponse::class
        );
        $middleware->alias([
            'active' => ActiveUser::class,
            'token.version' => CheckTokenVersion::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (
            ValidationException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (
            ModelNotFoundException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        });

        $exceptions->render(function (
            NotFoundHttpException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
            ], 404);
        });

        $exceptions->render(function (
            UnauthorizedHttpException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        });

        $exceptions->render(function (
            Throwable $e,
            $request
        ) {
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
            ], 500);
        });

        $exceptions->render(function (
            TokenExpiredException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired',
            ], 401);
        });

        $exceptions->render(function (
            TokenInvalidException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
            ], 401);
        });

        $exceptions->render(function (
            JWTException $e,
            $request
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Token required',
            ], 401);
        });

    })
    ->create();
