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

/**
 * Optional Supabase JWT middleware — used on public routes that also have
 * admin-specific behaviour (e.g. GET /grant-rounds).
 *
 * Difference from VerifySupabaseToken:
 * - No token present → silently passes through (request continues as unauthenticated)
 * - Valid token present → decodes it and sets $request->user() so the controller
 *   can tell the caller is an admin and return the full data set
 * - Invalid / expired token → returns 401, same as the required middleware
 *
 * This lets us keep the route publicly accessible while still identifying admins
 * when they include their Bearer token.
 */
class OptionalVerifySupabaseToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request — the current HTTP request
     * @param  Closure  $next    — the next middleware / controller in the chain
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If no Authorization header was sent, treat this as a public request.
        // Don't block it — just let it continue without a user set.
        $token = $request->bearerToken();
        if (! $token) {
            return $next($request);
        }

        try {
            // A token was provided — verify it against Supabase's public keys.
            // The keys are cached for 1 hour (same as VerifySupabaseToken) to avoid
            // making an HTTP request on every API call.
            $keySet = $this->getSupabaseKeySet();

            // JWT::decode() checks the signature, expiry, and structure.
            // Throws an exception if anything is wrong.
            $decoded = JWT::decode($token, $keySet);

            // The 'sub' claim is the user's UUID — look them up in our profiles table.
            $user = User::find($decoded->sub);

            if ($user) {
                // Set the user on Laravel's Auth facade so $request->user() works
                // in any controller down the chain.
                Auth::setUser($user);
            }

        } catch (\Exception $e) {
            // A token was sent but it's invalid or expired.
            // Return 401 rather than silently treating them as a public visitor —
            // a bad token almost certainly means an expired session that needs a re-login.
            return response()->json([
                'error' => [
                    'code'    => 'invalid_token',
                    'message' => 'The authentication token is invalid or has expired. Please log in again.',
                ],
            ], 401);
        }

        return $next($request);
    }

    /**
     * Fetches and caches Supabase's public key set (JWKS).
     * Shares the same cache key as VerifySupabaseToken so both middlewares
     * benefit from a single cached copy.
     *
     * @return array<string, \Firebase\JWT\Key>
     */
    private function getSupabaseKeySet(): array
    {
        // Cache the raw JSON (not the parsed Key objects — PHP can't serialize those).
        $jwksJson = Cache::remember('supabase_jwks_json', 3600, function () {
            $supabaseUrl = config('services.supabase.url');
            return Http::get("{$supabaseUrl}/auth/v1/.well-known/jwks.json")->json();
        });

        return JWK::parseKeySet($jwksJson);
    }
}
