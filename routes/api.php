<?php

use App\Http\Controllers\Api\V1\AbnLookupController;
use App\Http\Controllers\Api\V1\AiChatController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\ApplicationDocumentController;
use App\Http\Controllers\Api\V1\ApplicationStatusHistoryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocumentRequestController;
use App\Http\Controllers\Api\V1\GrantRoundController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewNoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public auth routes.
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Public grant round reads. The optional middleware decodes a token when present so the
    // controller can identify admins and return the full data set.
    Route::middleware('auth.supabase.optional')->group(function () {
        Route::get('grant-rounds', [GrantRoundController::class, 'index']);
        Route::get('grant-rounds/{grantRound}', [GrantRoundController::class, 'show']);
    });

    // Protected routes. auth.supabase verifies the Supabase JWT and returns 401 on failure.
    Route::middleware('auth.supabase')->group(function () {

        // Grant round writes (admin only). Public index + show are wired above.
        Route::apiResource('grant-rounds', GrantRoundController::class)
            ->except(['index', 'show']);

        // Applications: applicants manage their own, admins see all.
        Route::apiResource('applications', ApplicationController::class);

        // One-way draft to submitted transition.
        Route::post('applications/{application}/submit', [ApplicationController::class, 'submit']);

        // Applicant pulls a submitted/under_review application out of the pipeline.
        Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw']);

        // Admin status changes for the review workflow.
        Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus']);

        // Application documents. 'shallow' makes destroy live at /documents/{id}.
        Route::apiResource('applications.documents', ApplicationDocumentController::class)
            ->only(['index', 'store', 'destroy'])
            ->shallow();

        // Read-only audit trail of status changes.
        Route::get(
            'applications/{application}/status-history',
            [ApplicationStatusHistoryController::class, 'index']
        );

        // Admin review notes. Nested for create/list, shallow for edit/delete.
        Route::apiResource('applications.review-notes', ReviewNoteController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->shallow();

        // Admin-driven document requests. POST + GET nested under applications,
        // PATCH lives at /document-requests/{id} via shallow nesting.
        Route::apiResource('applications.document-requests', DocumentRequestController::class)
            ->only(['index', 'store', 'update'])
            ->shallow();

        // Per-user in-app notification inbox.
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // The authenticated user's own profile.
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        // Australian Business Register lookup used by the profile ABN field.
        // The {abn} segment is constrained to 11 digits so anything else 404s at the router.
        Route::get('abn-lookup/{abn}', [AbnLookupController::class, 'show'])
            ->where('abn', '\d{11}');

        // AI chatbot streaming endpoint. The controller does its own ownership check.
        Route::post('ai/chat', [AiChatController::class, 'chat']);
    });
});
