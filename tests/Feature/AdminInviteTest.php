<?php

namespace Tests\Feature;

use App\Mail\AdminInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The invite/resend paths hit Supabase generate_link; login hits the token endpoint.
        config()->set('services.supabase.url', 'https://fake.supabase.co');
        config()->set('services.supabase.anon_key', 'fake-anon-key');
        config()->set('services.supabase.service_key', 'fake-service-key');
    }

    // An existing, already-accepted admin to perform the privileged actions.
    private function actingAdmin(): User
    {
        return User::factory()->create([
            'role'               => 'admin',
            'invite_accepted_at' => now(),
        ]);
    }

    // Supabase generate_link returns an action link plus the (new or existing) user id.
    private function fakeGenerateLink(?string $userId = null): void
    {
        Http::fake([
            'fake.supabase.co/auth/v1/admin/generate_link' => Http::response([
                'action_link' => 'https://fake.supabase.co/auth/v1/verify?token=abc&type=invite',
                'user'        => ['id' => $userId ?? (string) Str::uuid()],
            ], 200),
        ]);
    }

    public function test_inviting_a_new_email_creates_a_pending_admin_and_sends_mail(): void
    {
        Mail::fake();
        $newId = (string) Str::uuid();
        $this->fakeGenerateLink($newId);

        $response = $this->actingAsUser($this->actingAdmin())
            ->postJson('/api/v1/admin/admins', ['email' => 'invitee@example.com']);

        $response->assertStatus(201)
            ->assertJsonPath('action', 'invited')
            ->assertJsonPath('data.pending', true);

        // Profile created as a pending admin (no acceptance timestamp yet).
        $this->assertDatabaseHas('profiles', [
            'id'                 => $newId,
            'email'              => 'invitee@example.com',
            'role'               => 'admin',
            'invite_accepted_at' => null,
        ]);

        Mail::assertSent(AdminInvite::class, fn (AdminInvite $m) => $m->hasTo('invitee@example.com'));
    }

    public function test_promoting_an_existing_user_is_active_not_pending(): void
    {
        Mail::fake();
        $applicant = User::factory()->create([
            'email'              => 'member@example.com',
            'role'               => 'applicant',
            'invite_accepted_at' => now(),
        ]);

        $response = $this->actingAsUser($this->actingAdmin())
            ->postJson('/api/v1/admin/admins', ['email' => 'member@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('action', 'promoted')
            ->assertJsonPath('data.pending', false);

        $this->assertSame('admin', $applicant->fresh()->role);
        // Promotion is not an email invite, so no AdminInvite goes out.
        Mail::assertNotSent(AdminInvite::class);
    }

    public function test_index_flags_pending_vs_active_admins(): void
    {
        $acting  = $this->actingAdmin();
        $pending = User::factory()->create([
            'email'              => 'pending@example.com',
            'role'               => 'admin',
            'invite_accepted_at' => null,
        ]);

        $response = $this->actingAsUser($acting)->getJson('/api/v1/admin/users');
        $response->assertStatus(200);

        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertTrue($byId[$pending->id]['pending']);
        $this->assertFalse($byId[$acting->id]['pending']);
    }

    public function test_first_login_marks_invited_admin_as_accepted(): void
    {
        $user = User::factory()->create([
            'email'              => 'fresh@example.com',
            'role'               => 'admin',
            'invite_accepted_at' => null,
        ]);

        Http::fake([
            'fake.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'fake-jwt',
                'expires_in'   => 3600,
                'user'         => ['id' => $user->id],
            ], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'fresh@example.com',
            'password' => 'correct-password',
        ])->assertStatus(200);

        // Pending -> Active after the first successful sign-in.
        $this->assertNotNull($user->fresh()->invite_accepted_at);
    }

    public function test_resend_sends_a_fresh_invite_for_a_pending_admin(): void
    {
        Mail::fake();
        $this->fakeGenerateLink();

        $pending = User::factory()->create([
            'email'              => 'pending@example.com',
            'role'               => 'admin',
            'invite_accepted_at' => null,
        ]);

        $this->actingAsUser($this->actingAdmin())
            ->postJson("/api/v1/admin/admins/{$pending->id}/resend")
            ->assertStatus(200)
            ->assertJsonStructure(['message']);

        Mail::assertSent(AdminInvite::class, fn (AdminInvite $m) => $m->hasTo('pending@example.com'));
    }

    public function test_resend_is_blocked_once_the_admin_has_accepted(): void
    {
        Mail::fake();
        $accepted = User::factory()->create([
            'email'              => 'accepted@example.com',
            'role'               => 'admin',
            'invite_accepted_at' => now(),
        ]);

        $this->actingAsUser($this->actingAdmin())
            ->postJson("/api/v1/admin/admins/{$accepted->id}/resend")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'role_change_not_allowed');

        Mail::assertNotSent(AdminInvite::class);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $applicant = User::factory()->create([
            'role'               => 'applicant',
            'invite_accepted_at' => now(),
        ]);

        $this->actingAsUser($applicant)
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
}
