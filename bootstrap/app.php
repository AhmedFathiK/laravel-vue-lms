<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
            return route('login');
        });

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\DetectLocaleFromRequest::class,
            \App\Http\Middleware\ConvertCamelCase::class,
            \App\Http\Middleware\CamelCaseResponse::class,
        ]);
        // Enable CORS middleware
        $middleware->web(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->api(append: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle ModelNotFoundException for JSON responses
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                    'error' => 'The requested resource could not be found',
                    'details' => $e->getMessage()
                ], 404);
            }
        });

        // Handle NotFoundHttpException for JSON responses
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Route not found',
                    'error' => 'The requested route could not be found',
                    'details' => $e->getMessage(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                ], 404);
            }
        });
    })->create();
