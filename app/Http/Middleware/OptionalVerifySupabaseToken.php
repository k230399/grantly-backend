<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

// Optional version of VerifySupabaseToken for public routes that also have admin-specific behaviour
// (e.g. GET /grant-rounds). No token: pass through unauthenticated. Valid token: set the user.
// Invalid token: still 401, since a bad token almost always means an expired session.
class OptionalVerifySupabaseToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $next($request);
        }

        try {
            $keySet  = $this->getSupabaseKeySet();
            $decoded = JWT::decode($token, $keySet);

            $user = User::find($decoded->sub);
            if ($user) {
                Auth::setUser($user);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code'    => 'invalid_token',
                    'message' => 'The authentication token is invalid or has expired. Please log in again.',
                ],
            ], 401);
        }

        return $next($request);
    }

    private function getSupabaseKeySet(): array
    {
        // Shares the JWKS cache key with VerifySupabaseToken so both middlewares hit the same copy.
        $jwksJson = Cache::remember('supabase_jwks_json', 3600, function () {
            $supabaseUrl = config('services.supabase.url');
            return Http::get("{$supabaseUrl}/auth/v1/.well-known/jwks.json")->json();
        });

        return JWK::parseKeySet($jwksJson);
    }
}
