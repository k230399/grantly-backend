<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_can_withdraw_submitted_application(): void
    {
        $applicant = User::factory()->create();
        $admin     = User::factory()->admin()->create();
        $round     = GrantRound::factory()->create();
        $app       = Application::factory()->submitted()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$app->id}/withdraw", [
                'reason' => 'Project no longer running.',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('applications', [
            'id'     => $app->id,
            'status' => 'withdrawn',
        ]);

        // Audit log records the withdraw, with the reason on notes.
        $this->assertDatabaseHas('application_status_history', [
            'application_id'  => $app->id,
            'previous_status' => 'submitted',
            'new_status'      => 'withdrawn',
            'changed_by'      => $applicant->id,
            'notes'           => 'Project no longer running.',
        ]);

        // Applicant + admin fan-out notifications fired.
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $applicant->id,
            'application_id' => $app->id,
            'type'           => 'application_withdrawn',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $admin->id,
            'application_id' => $app->id,
            'type'           => 'application_withdrawn',
        ]);
    }

    public function test_cannot_withdraw_a_draft_application(): void
    {
        $applicant = User::factory()->create();
        $app       = Application::factory()->create([
            'applicant_id' => $applicant->id,
            'status'       => 'draft',
        ]);

        $response = $this->actingAsUser($applicant)
            ->postJson("/api/v1/applications/{$app->id}/withdraw");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'not_withdrawable');
    }

    public function test_cannot_withdraw_someone_elses_application(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $app      = Application::factory()->submitted()->create([
            'applicant_id' => $owner->id,
        ]);

        $response = $this->actingAsUser($intruder)
            ->postJson("/api/v1/applications/{$app->id}/withdraw");

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
}
