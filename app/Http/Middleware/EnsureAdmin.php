<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gate for admin-only routes. Expects to run after 'auth.supabase', which has already
// verified the JWT and attached the profile via Auth::setUser(). Returns 403 in the
// project's standard error shape for any non-admin (or unauthenticated) request.
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'This action requires an administrator account.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
