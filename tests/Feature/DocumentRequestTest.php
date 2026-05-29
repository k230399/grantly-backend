<?php

namespace Tests\Feature;

use App\Mail\DocumentRequested;
use App\Models\Application;
use App\Models\DocumentRequest;
use App\Models\GrantRound;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DocumentRequestTest extends TestCase
{
    use RefreshDatabase;

    // SupabaseStorageService talks to a real Supabase Storage bucket; in tests we swap
    // it for a Mockery double so upload() and signedUrl() are no-ops.
    protected function setUp(): void
    {
        parent::setUp();

        // The document-request endpoint emails the applicant inline; fake the mailer so tests
        // don't make real Resend calls.
        Mail::fake();

        $storage = Mockery::mock(SupabaseStorageService::class);
        $storage->shouldReceive('upload')->andReturnNull();
        $storage->shouldReceive('signedUrl')->andReturn('https://example.com/signed-url');
        $storage->shouldReceive('delete')->andReturnNull();
        $this->app->instance(SupabaseStorageService::class, $storage);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_admin_can_create_document_request_and_applicant_is_notified(): void
    {
        $admin     = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $round     = GrantRound::factory()->create();
        $app       = Application::factory()->submitted()->create([
            'applicant_id'   => $applicant->id,
            'grant_round_id' => $round->id,
        ]);

        $response = $this->actingAsUser($admin)
            ->postJson("/api/v1/applications/{$app->id}/document-requests", [
                'label'       => 'Updated Budget',
                'description' => 'Include line items for FY2026-27.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Updated Budget')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('document_requests', [
            'application_id' => $app->id,
            'requested_by'   => $admin->id,
            'label'          => 'Updated Budget',
            'status'         => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id'        => $applicant->id,
            'application_id' => $app->id,
            'type'           => 'document_requested',
        ]);

        // The applicant is also emailed about the request.
        Mail::assertSent(
            DocumentRequested::class,
            fn (DocumentRequested $m) => $m->hasTo($applicant->email)
        );
    }

    public function test_request_creation_blocked_on_draft_application(): void
    {
        $admin = User::factory()->admin()->create();
        $app   = Application::factory()->create(['status' => 'draft']);

        $response = $this->actingAsUser($admin)
            ->postJson("/api/v1/applications/{$app->id}/document-requests", [
                'label' => 'Anything',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'application_not_open_for_requests');
    }

    public function test_applicant_can_upload_against_pending_request_on_submitted_app(): void
    {
        $admin     = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $app       = Application::factory()->submitted()->create([
            'applicant_id' => $applicant->id,
        ]);
        $request = DocumentRequest::create([
            'application_id' => $app->id,
            'requested_by'   => $admin->id,
            'label'          => 'Insurance Certificate',
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        $file = UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf');

        $response = $this->actingAsUser($applicant)
            ->post("/api/v1/applications/{$app->id}/documents", [
                'file'                => $file,
                'document_request_id' => $request->id,
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('application_documents', [
            'application_id'      => $app->id,
            'document_type'       => 'admin_request',
            'document_request_id' => $request->id,
            'file_name'           => 'insurance.pdf',
        ]);

        // Admin fan-out fires on the upload.
        $this->assertDatabaseHas('notifications', [
            'user_id'        => $admin->id,
            'application_id' => $app->id,
            'type'           => 'document_uploaded',
        ]);
    }

    public function test_admin_marks_request_fulfilled_after_upload(): void
    {
        $admin     = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $app       = Application::factory()->submitted()->create([
            'applicant_id' => $applicant->id,
        ]);
        $request = DocumentRequest::create([
            'application_id' => $app->id,
            'requested_by'   => $admin->id,
            'label'          => 'Audit Report',
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        // Applicant uploads first so the request has an attached file.
        $this->actingAsUser($applicant)
            ->post("/api/v1/applications/{$app->id}/documents", [
                'file'                => UploadedFile::fake()->create('audit.pdf', 50, 'application/pdf'),
                'document_request_id' => $request->id,
            ])->assertCreated();

        $response = $this->actingAsUser($admin)
            ->patchJson("/api/v1/document-requests/{$request->id}", [
                'status' => 'fulfilled',
            ]);

        $response->assertOk()->assertJsonPath('data.status', 'fulfilled');

        $this->assertDatabaseHas('document_requests', [
            'id'     => $request->id,
            'status' => 'fulfilled',
        ]);
        $this->assertNotNull($request->fresh()->fulfilled_at);
    }

    public function test_cannot_mark_fulfilled_without_attached_file(): void
    {
        $admin = User::factory()->admin()->create();
        $app   = Application::factory()->submitted()->create();
        $request = DocumentRequest::create([
            'application_id' => $app->id,
            'requested_by'   => $admin->id,
            'label'          => 'Empty Request',
            'status'         => 'pending',
            'requested_at'   => now(),
        ]);

        $response = $this->actingAsUser($admin)
            ->patchJson("/api/v1/document-requests/{$request->id}", [
                'status' => 'fulfilled',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'no_document_attached');
    }
}
