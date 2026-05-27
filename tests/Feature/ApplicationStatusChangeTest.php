<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_admin_can_change_status_and_audit_log_records_transition(): void
    {
        $admin     = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create(['status' => 'open']);
        $app       = Application::factory()->submitted()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
        ]);

        $response = $this->actingAsUser($admin)
            ->patchJson("/api/v1/applications/{$app->id}/status", [
                'status' => 'under_review',
                'notes'  => 'Initial reviewer pass.',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('applications', [
            'id'     => $app->id,
            'status' => 'under_review',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id'  => $app->id,
            'previous_status' => 'submitted',
            'new_status'      => 'under_review',
            'changed_by'      => $admin->id,
            'notes'           => 'Initial reviewer pass.',
        ]);

        // Applicant gets the in-app notification.
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $applicant->id,
            'application_id' => $app->id,
            'type'           => 'application_status_changed',
        ]);
    }

    public function test_applicant_cannot_change_status(): void
    {
        $applicant = User::factory()->create();
        $app       = Application::factory()->submitted()->create([
            'applicant_id' => $applicant->id,
        ]);

        $response = $this->actingAsUser($applicant)
            ->patchJson("/api/v1/applications/{$app->id}/status", [
                'status' => 'approved',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->assertDatabaseHas('applications', [
            'id'     => $app->id,
            'status' => 'submitted',
        ]);
    }

    public function test_no_op_transition_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $app   = Application::factory()->submitted()->create();

        $response = $this->actingAsUser($admin)
            ->patchJson("/api/v1/applications/{$app->id}/status", [
                'status' => 'submitted',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'no_status_change');
    }
}
