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
 * Middleware that protects routes by verifying the Supabase JWT token.
 *
 * How it works:
 * 1. The frontend includes the JWT in the Authorization header: "Bearer <token>"
 * 2. This middleware fetches Supabase's public keys (JWKS) and uses them to verify
 *    that the token was genuinely issued by our Supabase project and hasn't been tampered with.
 * 3. If valid, it finds the matching user in our 'profiles' table and sets them
 *    as the authenticated user so controllers can call $request->user().
 * 4. If anything is wrong (missing token, bad signature, expired), it returns 401.
 *
 * Why JWKS instead of the JWT secret?
 * Newer Supabase projects use RS256 (asymmetric encryption) rather than HS256 (a shared secret).
 * RS256 uses a private key to sign tokens and a public key to verify them. Supabase publishes
 * its public keys at a standard URL called the JWKS endpoint. Using JWKS works for both
 * RS256 and HS256 automatically, so this approach is future-proof.
 */
class VerifySupabaseToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request — the current HTTP request
     * @param  Closure  $next    — the next middleware / controller in the chain
     * @return Response          — either a 401 error or the result of continuing the request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // bearerToken() reads the value after "Bearer " in the Authorization header.
        // Returns null if the header is missing or not a Bearer token.
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
            // Fetch Supabase's public keys and use them to verify the JWT.
            // The keys are cached for 1 hour to avoid making an HTTP request on every API call.
            $keySet = $this->getSupabaseKeySet();

            // Decode and verify the JWT.
            // JWT::decode() checks the signature, expiry, and structure automatically.
            // If anything is wrong it throws an exception, caught below.
            $decoded = JWT::decode($token, $keySet);

            // The 'sub' (subject) claim in a Supabase JWT is the user's UUID —
            // the same UUID stored in our 'profiles' table at registration.
            $userId = $decoded->sub;

            // Look up the user in our database using that UUID
            $user = User::find($userId);

            if (! $user) {
                // This can happen if the Supabase account exists but the profile row was
                // never created (e.g. a failed registration). Return a clear error.
                return response()->json([
                    'error' => [
                        'code'    => 'profile_not_found',
                        'message' => 'Account setup is incomplete. Please contact support.',
                    ],
                ], 401);
            }

            // Set the authenticated user on Laravel's Auth facade.
            // After this line, $request->user() in any controller will return this user object.
            Auth::setUser($user);

        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code'    => 'invalid_token',
                    'message' => 'The authentication token is invalid or has expired. Please log in again.',
                ],
            ], 401);
        }

        // Token is valid and user is set — continue to the next middleware or controller
        return $next($request);
    }

    /**
     * Fetches and caches Supabase's public key set (JWKS).
     *
     * JWKS stands for "JSON Web Key Set" — it's a standard format for publishing
     * the public keys used to verify JWTs. Supabase hosts theirs at a predictable URL.
     *
     * We cache the result for 1 hour so we don't make an HTTP request on every API call.
     * Supabase very rarely rotates their signing keys, so 1 hour is safe.
     *
     * @return array<string, \Firebase\JWT\Key> — a key set ready to pass to JWT::decode()
     */
    private function getSupabaseKeySet(): array
    {
        // Cache the raw JSON from Supabase, not the parsed Key objects.
        // PHP cannot serialize Firebase\JWT\Key objects — they come back as
        // __PHP_Incomplete_Class when read from cache, which causes a TypeError.
        // Caching the JSON and re-parsing it on each request is cheap and safe.
        $jwksJson = Cache::remember('supabase_jwks_json', 3600, function () {
            $supabaseUrl = config('services.supabase.url');

            // Fetch the JWKS from Supabase's well-known URL (public endpoint, no auth needed)
            return Http::get("{$supabaseUrl}/auth/v1/.well-known/jwks.json")->json();
        });

        // Parse the cached JSON into Key objects fresh on each request
        return JWK::parseKeySet($jwksJson);
    }
}
