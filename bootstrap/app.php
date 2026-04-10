<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Enable Sanctum's stateful cookie auth for first-party SPA/API requests.
        $middleware->statefulApi();

        // Add StartSession to API routes so that session('stego_mkd') is
        // available in stateless API requests (required for StegoLock MKD).
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // ── W3-T11: Centralized JSON error responses for all API routes ────────

        // 401 — Unauthenticated (no valid token, or session expired)
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // 403 — Authorization policy denied
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage() ?: 'This action is unauthorized.'], 403);
            }
        });

        // 404 — Model not found (findOrFail / route model binding)
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json(['message' => "{$model} not found."], 404);
            }
        });

        // 404 — Route not found
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Endpoint not found.'], 404);
            }
        });

        // 422 — Validation errors (consistent JSON envelope)
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 405 — Method not allowed
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $payload = ['message' => 'Method not allowed.'];

                if ((bool) config('app.debug')) {
                    $payload['method'] = $request->method();
                    $payload['path'] = $request->path();
                    $payload['allowed_methods'] = $e->getHeaders()['Allow'] ?? null;
                }

                return response()->json($payload, 405);
            }
        });

        // 500 — Catch-all for unhandled server errors on API routes
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                /** @var \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|null $httpEx */
                $httpEx  = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e : null;
                $status  = $httpEx ? $httpEx->getStatusCode() : 500;
                $message = $status < 500 ? $e->getMessage() : 'An unexpected error occurred.';
                return response()->json(['message' => $message], $status);
            }
        });
    })->create();
