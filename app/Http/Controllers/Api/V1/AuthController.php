<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    // POST /api/v1/auth/register
    // Creates a new applicant account by proxying to Supabase Auth and mirroring the user
    // into our profiles table. Returns 201 with a verification-email message on success;
    // maps Supabase's free-text errors to machine-readable codes the frontend can act on.
    public function register(RegisterRequest $request): JsonResponse
    {
        $supabaseUrl = config('services.supabase.url');
        $anonKey     = config('services.supabase.anon_key');

        // Supabase handles password hashing and sends the verification email when enabled.
        $response = Http::withHeaders([
            'apikey'       => $anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/signup", [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        // Map Supabase's free-text errors to machine-readable codes the frontend can act on.
        if ($response->failed()) {
            $body = $response->json();
            $msg  = $body['msg'] ?? $body['message'] ?? 'Registration failed.';
            $lower = strtolower($msg);

            $code = match (true) {
                str_contains($lower, 'already registered') => 'email_taken',
                str_contains($lower, 'password')           => 'weak_password',
                str_contains($lower, 'rate limit')         => 'rate_limited',
                default                                    => 'registration_failed',
            };

            $status = $code === 'rate_limited' ? 429 : 422;

            return response()->json([
                'error' => ['code' => $code, 'message' => $msg],
            ], $status);
        }

        // Supabase returns the user at root when email confirmation is required,
        // and nested under 'user' when a session is issued immediately.
        $userId = $response->json('id') ?? $response->json('user.id');

        // Mirror the Supabase user into our profiles table using the same UUID.
        try {
            User::create([
                'id'        => $userId,
                'email'     => $request->email,
                'full_name' => $request->full_name,
                'role'      => 'applicant',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // When email confirmation is disabled Supabase returns 200 for an existing
            // email instead of an error, so the unique-violation surfaces here (23505).
            if ($e->getCode() === '23505') {
                return response()->json([
                    'error' => ['code' => 'email_taken', 'message' => 'An account with this email already exists.'],
                ], 422);
            }

            return response()->json([
                'error' => [
                    'code'    => 'profile_creation_failed',
                    'message' => 'Account was created but profile setup failed. Please contact support.',
                ],
            ], 500);
        }

        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.',
        ], 201);
    }

    // POST /api/v1/auth/login
    // Exchanges email + password for a Supabase JWT. Returns access_token + the user
    // profile so the frontend can route into /dashboard or /admin without a second call.
    // Wrong-email and wrong-password both surface as invalid_credentials (no probe leak).
    public function login(LoginRequest $request): JsonResponse
    {
        $supabaseUrl = config('services.supabase.url');
        $anonKey     = config('services.supabase.anon_key');

        $response = Http::withHeaders([
            'apikey'       => $anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/token?grant_type=password", [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            $body = $response->json();
            // Supabase moves the error message between fields across versions.
            $msg  = strtolower($body['error_description'] ?? $body['msg'] ?? $body['message'] ?? '');

            // Wrong password and unknown email return the same code so attackers can't probe registrations.
            [$code, $friendlyMessage] = match (true) {
                str_contains($msg, 'invalid login credentials') => [
                    'invalid_credentials',
                    'The email or password you entered is incorrect.',
                ],
                str_contains($msg, 'email not confirmed') => [
                    'email_not_confirmed',
                    'Please verify your email address before logging in.',
                ],
                str_contains($msg, 'rate limit') => [
                    'rate_limited',
                    'Too many login attempts. Please wait a moment and try again.',
                ],
                default => [
                    'login_failed',
                    'Login failed. Please try again.',
                ],
            };

            $status = $code === 'rate_limited' ? 429 : 401;

            return response()->json([
                'error' => ['code' => $code, 'message' => $friendlyMessage],
            ], $status);
        }

        $accessToken = $response->json('access_token');
        $expiresIn   = $response->json('expires_in');
        $userId      = $response->json('user.id');

        // Return the profile alongside the token so the frontend has name and role immediately.
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'error' => [
                    'code'    => 'profile_not_found',
                    'message' => 'Account setup is incomplete. Please contact support.',
                ],
            ], 500);
        }

        return response()->json([
            'access_token' => $accessToken,
            'token_type'   => 'bearer',
            'expires_in'   => $expiresIn,
            'user'         => [
                'id'        => $user->id,
                'email'     => $user->email,
                'full_name' => $user->full_name,
                'role'      => $user->role,
            ],
        ]);
    }
}
