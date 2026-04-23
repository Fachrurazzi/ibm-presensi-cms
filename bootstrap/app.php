<?php

use App\Http\Middleware\CheckApiDocAccess;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies untuk production
        $middleware->trustProxies(at: '*');

        // Global middleware
        $middleware->append([
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'api-doc-access' => CheckApiDocAccess::class,
            'is_admin'       => IsAdmin::class,
            'role'           => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'     => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // API middleware group
        // ✅ HAPUS throttle:api - gunakan rate limiter manual di controller
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API Exception Handling
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Unauthenticated. Silakan login terlebih dahulu.',
                    'timestamp' => now()->toIso8601String(),
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Resource tidak ditemukan.',
                    'timestamp' => now()->toIso8601String(),
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Validasi gagal.',
                    'errors'    => $e->errors(),
                    'timestamp' => now()->toIso8601String(),
                ], 422);
            }
        });

        // Handle general exceptions for API
        $exceptions->render(function (\Exception $e, $request) {
            if ($request->expectsJson() && !app()->environment('local')) {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Terjadi kesalahan pada server.',
                    'timestamp' => now()->toIso8601String(),
                ], 500);
            }
        });
    })
    ->create();
