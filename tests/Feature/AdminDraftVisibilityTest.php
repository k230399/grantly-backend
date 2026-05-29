<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Drafts are the applicant's private work-in-progress: admins must never see or act on them,
// while applicants keep full access to their own.
class AdminDraftVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function seedScenario(): array
    {
        $admin     = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create(['status' => 'open']);

        $draft = Application::factory()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
            'status'         => 'draft',
        ]);
        $submitted = Application::factory()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
            'status'         => 'submitted',
        ]);

        return compact('admin', 'applicant', 'draft', 'submitted');
    }

    public function test_admin_index_excludes_drafts(): void
    {
        ['admin' => $admin, 'draft' => $draft, 'submitted' => $submitted] = $this->seedScenario();

        $response = $this->actingAsUser($admin)->getJson('/api/v1/applications');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($submitted->id), 'submitted application should be listed');
        $this->assertFalse($ids->contains($draft->id), 'draft application should NOT be listed for admins');
    }

    public function test_admin_cannot_view_a_draft(): void
    {
        ['admin' => $admin, 'draft' => $draft] = $this->seedScenario();

        $this->actingAsUser($admin)
            ->getJson("/api/v1/applications/{$draft->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_admin_cannot_change_status_of_a_draft(): void
    {
        ['admin' => $admin, 'draft' => $draft] = $this->seedScenario();

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/applications/{$draft->id}/status", ['status' => 'under_review'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');

        // The draft's status is untouched.
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_admin_can_view_a_submitted_application(): void
    {
        ['admin' => $admin, 'submitted' => $submitted] = $this->seedScenario();

        $this->actingAsUser($admin)
            ->getJson("/api/v1/applications/{$submitted->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $submitted->id);
    }

    public function test_applicant_still_sees_their_own_draft(): void
    {
        ['applicant' => $applicant, 'draft' => $draft] = $this->seedScenario();

        // Visible in their list...
        $ids = collect(
            $this->actingAsUser($applicant)->getJson('/api/v1/applications')->json('data')
        )->pluck('id');
        $this->assertTrue($ids->contains($draft->id));

        // ...and openable directly.
        $this->actingAsUser($applicant)
            ->getJson("/api/v1/applications/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $draft->id);
    }
}
