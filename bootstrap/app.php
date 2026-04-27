<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register a named alias for our Supabase JWT middleware.
        // This lets us write middleware('auth.supabase') in routes instead of
        // the full class name — same idea as the built-in 'auth:sanctum' shorthand.
        $middleware->alias([
            'auth.supabase'          => \App\Http\Middleware\VerifySupabaseToken::class,
            // Optional variant — sets $request->user() when a token is present but
            // does not block the request when no token is provided. Used on public
            // routes that also have admin-specific behaviour (e.g. GET /grant-rounds).
            'auth.supabase.optional' => \App\Http\Middleware\OptionalVerifySupabaseToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Reformat Laravel's default validation error response to match our API's
        // standard error shape: { error: { code, message, details } }.
        // This applies to every endpoint automatically — no per-controller handling needed.
        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'validation_error',
                    'message' => $e->getMessage(),
                    'details' => $e->errors(), // field-level messages, e.g. { email: ["required"] }
                ],
            ], 422);
        });

        // If Supabase (or any external HTTP call) is unreachable, return a clean 503
        // instead of letting an unhandled exception crash the request.
        $exceptions->render(function (ConnectionException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'service_unavailable',
                    'message' => 'A required service is currently unreachable. Please try again later.',
                ],
            ], 503);
        });

    })->create();
