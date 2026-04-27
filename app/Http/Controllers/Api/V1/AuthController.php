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
    public function register(RegisterRequest $request): JsonResponse
    {
        // Pull Supabase connection details from config/services.php,
        // which reads them from the .env file.
        $supabaseUrl = config('services.supabase.url');
        $anonKey     = config('services.supabase.anon_key');

        // Call Supabase Auth to create the user account.
        // Supabase handles password hashing and (when enabled) sends the confirmation email.
        $response = Http::withHeaders([
            'apikey'       => $anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/signup", [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        // If Supabase rejected the request, parse the error and return a clear response.
        if ($response->failed()) {
            $body = $response->json();
            $msg  = $body['msg'] ?? $body['message'] ?? 'Registration failed.';
            $lower = strtolower($msg);

            // Map Supabase's error messages to machine-readable codes the frontend can act on.
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
        // or nested under 'user' when a session is issued immediately.
        $userId = $response->json('id') ?? $response->json('user.id');

        // Create a matching row in our profiles table using the same UUID
        // that Supabase assigned to the user in auth.users.
        // Wrapped in try/catch so a DB failure returns a clean error instead of a crash.
        try {
            User::create([
                'id'        => $userId,
                'email'     => $request->email,
                'full_name' => $request->full_name,
                'role'      => 'applicant', // all self-registered users start as applicants
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Postgres error code 23505 means a unique constraint was violated.
            // This happens when email confirmation is disabled — Supabase returns a 200
            // for an existing email instead of an error, so we catch the duplicate here.
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

    public function login(LoginRequest $request): JsonResponse
    {
        // Pull Supabase connection details from config/services.php.
        $supabaseUrl = config('services.supabase.url');
        $anonKey     = config('services.supabase.anon_key');

        // Call Supabase Auth to validate the credentials and issue a JWT.
        // The grant_type=password flow is the standard username/password login.
        $response = Http::withHeaders([
            'apikey'       => $anonKey,
            'Content-Type' => 'application/json',
        ])->post("{$supabaseUrl}/auth/v1/token?grant_type=password", [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        // If Supabase rejected the credentials, map the error to a friendly message.
        if ($response->failed()) {
            $body  = $response->json();
            // Supabase uses different fields across versions — check all known locations.
            $msg = strtolower($body['error_description'] ?? $body['msg'] ?? $body['message'] ?? '');

            // Supabase deliberately returns the same error for wrong password AND unknown email
            // — this prevents attackers from probing which emails are registered.
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

        // Extract the JWT access token from the Supabase response.
        // The frontend will attach this token to every subsequent API request as a Bearer token.
        $accessToken = $response->json('access_token');
        $expiresIn   = $response->json('expires_in');
        $userId      = $response->json('user.id');

        // Fetch the user's profile from our profiles table using the Supabase user ID.
        // We return this alongside the token so the frontend knows the user's name and role immediately.
        // If the profile is missing (e.g. created in Supabase but DB insert failed), return a clear error.
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
