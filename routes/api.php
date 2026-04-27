<?php

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\ApplicationDocumentController;
use App\Http\Controllers\Api\V1\ApplicationStatusHistoryController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GrantRoundController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReviewNoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Public auth routes (no token required) ───────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']); // Create a new applicant account
        Route::post('login', [AuthController::class, 'login']);       // Authenticate and receive a JWT
    });

    // ─── Public grant round read routes ───────────────────────────────────────
    // Open to everyone — no token required to browse. The optional middleware
    // decodes the token when one IS present so the controller can identify admins
    // and return the full data set (all statuses, extra fields). When no token is
    // sent the middleware is a no-op and the controller returns only open/published rounds.
    Route::middleware('auth.supabase.optional')->group(function () {
        Route::get('grant-rounds', [GrantRoundController::class, 'index']);
        Route::get('grant-rounds/{grantRound}', [GrantRoundController::class, 'show']);
    });

    // ─── Protected routes (valid Supabase JWT required) ───────────────────────
    // 'auth.supabase' is our custom middleware (VerifySupabaseToken) that decodes
    // the Supabase JWT from the Authorization header and identifies the user.
    // If no token or an invalid token is provided, it returns a 401 automatically.
    Route::middleware('auth.supabase')->group(function () {

        // Grant Rounds write operations — admins only (index + show are public above)
        // Generates: POST /grant-rounds, PUT/PATCH /grant-rounds/{id}, DELETE /grant-rounds/{id}
        Route::apiResource('grant-rounds', GrantRoundController::class)
            ->except(['index', 'show']);

        // Applications — applicants manage their own; admins see all
        // Generates: GET /applications, GET /applications/{id},
        //            POST /applications, PUT/PATCH /applications/{id}, DELETE /applications/{id}
        Route::apiResource('applications', ApplicationController::class);

        // Submit action — transitions a draft application to "submitted" (cannot be undone)
        Route::post('applications/{application}/submit', [ApplicationController::class, 'submit']);

        // Application Documents — nested under applications for upload/list
        // 'shallow' means destroy uses a top-level route /documents/{id} instead of the nested path
        // Generates: GET /applications/{application}/documents, POST /applications/{application}/documents
        //            DELETE /documents/{document}
        Route::apiResource('applications.documents', ApplicationDocumentController::class)
            ->only(['index', 'store', 'destroy'])
            ->shallow();

        // Application Status History — read-only audit trail
        Route::get(
            'applications/{application}/status-history',
            [ApplicationStatusHistoryController::class, 'index']
        );

        // Review Notes — admins write notes on applications; nested for create/list, shallow for edit/delete
        // Generates: GET /applications/{application}/review-notes, POST /applications/{application}/review-notes
        //            PUT/PATCH /review-notes/{note}, DELETE /review-notes/{note}
        Route::apiResource('applications.review-notes', ReviewNoteController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->shallow();

        // Notifications — each user's personal notification inbox
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    });
});
