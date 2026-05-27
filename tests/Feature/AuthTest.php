<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // Pretend Supabase config is set so the controller has a base URL to call.
    // The real network calls are intercepted by Http::fake() in each test.
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.supabase.url', 'https://fake.supabase.co');
        config()->set('services.supabase.anon_key', 'fake-anon-key');
    }

    public function test_register_creates_profile_and_returns_success(): void
    {
        $supabaseUserId = (string) Str::uuid();

        Http::fake([
            'fake.supabase.co/auth/v1/signup' => Http::response([
                'id'    => $supabaseUserId,
                'email' => 'new@example.com',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'full_name'             => 'New Applicant',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message']);

        // The profile row should mirror the Supabase user, with the role defaulted to applicant.
        $this->assertDatabaseHas('profiles', [
            'id'        => $supabaseUserId,
            'email'     => 'new@example.com',
            'full_name' => 'New Applicant',
            'role'      => 'applicant',
        ]);
    }

    public function test_register_maps_already_registered_to_email_taken_code(): void
    {
        Http::fake([
            'fake.supabase.co/auth/v1/signup' => Http::response([
                'msg' => 'User already registered',
            ], 422),
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'taken@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'full_name'             => 'Taken User',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'email_taken');

        // No profile should have been created for a failed signup.
        $this->assertDatabaseMissing('profiles', ['email' => 'taken@example.com']);
    }

    public function test_login_returns_access_token_and_user(): void
    {
        $user = User::factory()->create([
            'email'     => 'iz@example.com',
            'full_name' => 'Iz Quindo',
            'role'      => 'applicant',
        ]);

        Http::fake([
            'fake.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'fake-jwt-token',
                'expires_in'   => 3600,
                'user'         => ['id' => $user->id],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'iz@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('access_token', 'fake-jwt-token')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.full_name', 'Iz Quindo')
            ->assertJsonPath('user.role', 'applicant');
    }

    public function test_login_returns_invalid_credentials_on_bad_password(): void
    {
        Http::fake([
            'fake.supabase.co/auth/v1/token*' => Http::response([
                'error_description' => 'Invalid login credentials',
            ], 400),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'someone@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'invalid_credentials');
    }
}
