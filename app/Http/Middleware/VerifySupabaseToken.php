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

// Protects routes by verifying the Supabase JWT in the Authorization header.
// Sets $request->user() on success, returns 401 on missing/invalid token or unknown profile.
// JWKS works for both RS256 (new Supabase projects) and HS256, so the path is future-proof.
class VerifySupabaseToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => [
                    'code'    => 'unauthenticated',
                    'message' => 'No authentication token provided. Include a Bearer token in the Authorization header.',
                ],
            ], 401);
        }

        try {
            // JWT::decode verifies signature, expiry, and structure. Throws on any failure.
            $keySet  = $this->getSupabaseKeySet();
            $decoded = JWT::decode($token, $keySet);

            // The 'sub' claim is the Supabase user UUID, which matches profiles.id.
            $user = User::find($decoded->sub);

            if (! $user) {
                // Supabase account exists but the profile row was never created (e.g. failed registration).
                return response()->json([
                    'error' => [
                        'code'    => 'profile_not_found',
                        'message' => 'Account setup is incomplete. Please contact support.',
                    ],
                ], 401);
            }

            Auth::setUser($user);

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
        // Cache the raw JSON, not the parsed Key objects: PHP cannot serialize Firebase\JWT\Key.
        // Re-parsing on each request is cheap; Supabase rarely rotates its signing keys.
        $jwksJson = Cache::remember('supabase_jwks_json', 3600, function () {
            $supabaseUrl = config('services.supabase.url');
            return Http::get("{$supabaseUrl}/auth/v1/.well-known/jwks.json")->json();
        });

        return JWK::parseKeySet($jwksJson);
    }
}
