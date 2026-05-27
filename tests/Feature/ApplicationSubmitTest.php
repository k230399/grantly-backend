<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationSubmitTest extends TestCase
{
    use RefreshDatabase;

    // Resend / mailables are wrapped in try/catch already; we still fake Mail so the test
    // doesn't try to dispatch a real queued job to a real driver.
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_applicant_submits_draft_successfully(): void
    {
        $applicant = User::factory()->create();
        $admin     = User::factory()->admin()->create();
        $round     = GrantRound::factory()->create(['status' => 'open']);
        $draft     = Application::factory()->create([
            'applicant_id'         => $applicant->id,
            'grant_round_id'       => $round->id,
            'status'               => 'draft',
            'declaration_accepted' => true,
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$draft->id}/submit");

        $response->assertOk();

        // Status transition: draft → submitted, submitted_at stamped.
        $this->assertDatabaseHas('applications', [
            'id'     => $draft->id,
            'status' => 'submitted',
        ]);
        $this->assertNotNull($draft->fresh()->submitted_at);

        // Audit log entry recorded.
        $this->assertDatabaseHas('application_status_history', [
            'application_id'  => $draft->id,
            'previous_status' => 'draft',
            'new_status'      => 'submitted',
            'changed_by'      => $applicant->id,
        ]);

        // Applicant notified.
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $applicant->id,
            'application_id' => $draft->id,
            'type'           => 'application_submitted',
        ]);

        // Admin fan-out fired.
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $admin->id,
            'application_id' => $draft->id,
            'type'           => 'application_submitted',
        ]);
    }

    public function test_submit_rejects_when_declaration_not_accepted(): void
    {
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create(['status' => 'open']);
        $draft     = Application::factory()->create([
            'applicant_id'         => $applicant->id,
            'grant_round_id'       => $round->id,
            'status'               => 'draft',
            'declaration_accepted' => false,
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$draft->id}/submit");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'incomplete_application')
            ->assertJsonPath('error.details.missing_fields.0', 'declaration_accepted');

        // Status unchanged.
        $this->assertDatabaseHas('applications', [
            'id'     => $draft->id,
            'status' => 'draft',
        ]);
    }

    public function test_submit_rejects_when_grant_round_is_closed(): void
    {
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create(['status' => 'closed']);
        $draft     = Application::factory()->create([
            'applicant_id'         => $applicant->id,
            'grant_round_id'       => $round->id,
            'status'               => 'draft',
            'declaration_accepted' => true,
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$draft->id}/submit");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'grant_round_closed');
    }

    public function test_submit_rejects_already_submitted_application(): void
    {
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create(['status' => 'open']);
        $submitted = Application::factory()->submitted()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$submitted->id}/submit");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'already_submitted');
    }
}
