<?php

namespace Tests;

use App\Http\Middleware\OptionalVerifySupabaseToken;
use App\Http\Middleware\VerifySupabaseToken;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Authenticates the given user for the next request by disabling the JWT-verifying
    // middleware and falling through to Laravel's built-in actingAs. The middleware in
    // production would set the same Auth state after decoding a real Supabase token.
    protected function actingAsUser(User $user): static
    {
        $this->withoutMiddleware([
            VerifySupabaseToken::class,
            OptionalVerifySupabaseToken::class,
        ]);

        $this->actingAs($user);

        return $this;
    }
}
