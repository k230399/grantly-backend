<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GrantRound\StoreGrantRoundRequest;
use App\Http\Requests\GrantRound\UpdateGrantRoundRequest;
use App\Http\Resources\GrantRoundResource;
use App\Models\GrantRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles all grant round endpoints.
 * Admins can create, edit, publish, and close rounds.
 * Applicants can only list and view rounds that are currently open.
 * Endpoint prefix: /api/v1/grant-rounds
 */
class GrantRoundController extends Controller
{
    /**
     * GET /api/v1/grant-rounds
     *
     * Returns a paginated list of grant rounds.
     * Admins see all rounds in all statuses (draft, open, closed) and can filter by ?status=
     * Applicants see only open rounds — drafts and closed rounds are hidden.
     *
     * @param  Request $request — may include a ?status= query param (admins only)
     * @return JsonResponse     — paginated list of grant round objects with meta
     */
    public function index(Request $request): JsonResponse
    {
        // Get the authenticated user if a token was provided — null for public (unauthenticated) requests
        $user = $request->user();

        // Admins see all rounds regardless of status or published state.
        // Applicants and unauthenticated visitors see only open, published rounds.
        if ($user && $user->role === 'admin') {
            // Admins can see all rounds — eager-load the creator relationship
            // and count applications so the response has those details without extra queries.
            $query = GrantRound::with('creator')->withCount('applications');

            // Allow filtering by status via ?status=draft, ?status=open, or ?status=closed
            $statusFilter = $request->query('status');
            if ($statusFilter && in_array($statusFilter, ['draft', 'open', 'closed'])) {
                // Only apply the filter if it's a valid status value — silently ignore anything else
                $query->where('status', $statusFilter);
            }

            // Show newest rounds first
            $rounds = $query->orderBy('created_at', 'desc')->paginate(15);
        } else {
            // Applicants only see rounds that are both published AND currently open.
            // is_published = true means the admin has explicitly made it visible.
            // status = 'open' means it's currently accepting applications.
            // A round can be published but not yet open (or closed) — applicants shouldn't see those.
            $rounds = GrantRound::where('is_published', true)
                ->where('status', 'open')
                ->orderBy('opens_at', 'desc')
                ->paginate(15);
        }

        return response()->json([
            // Each round is shaped by GrantRoundResource before being serialised
            'data' => GrantRoundResource::collection($rounds),
            // Pagination info so the frontend knows how many pages exist
            'meta' => [
                'current_page' => $rounds->currentPage(),
                'last_page'    => $rounds->lastPage(),
                'per_page'     => $rounds->perPage(),
                'total'        => $rounds->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/grant-rounds/{id}
     *
     * Returns the full details of a single grant round.
     * Applicants can only view rounds with status "open".
     * Admins can view any round regardless of status.
     *
     * @param  Request    $request    — the incoming HTTP request
     * @param  GrantRound $grantRound — automatically resolved from the {id} URL segment
     *                                  via Laravel's model binding (404 if not found)
     * @return JsonResponse           — single grant round object, or 403 for applicants on non-open rounds
     */
    public function show(Request $request, GrantRound $grantRound): JsonResponse
    {
        // Get the authenticated user if a token was provided — null for public requests
        $user = $request->user();

        // Unauthenticated visitors and applicants can only view published rounds.
        // We don't restrict to status = 'open' here so a user can still read the
        // details of a closed round they previously applied to.
        if ((! $user || $user->role !== 'admin') && ! $grantRound->is_published) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this grant round.',
                ],
            ], 403);
        }

        // For admins, load extra context: who created the round and how many applications it has
        if ($user && $user->role === 'admin') {
            $grantRound->load('creator');
            $grantRound->loadCount('applications');
        }

        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ]);
    }

    /**
     * POST /api/v1/grant-rounds
     *
     * Creates a new grant round (admin only).
     * New rounds always start in "draft" status — they must be explicitly published
     * via the update endpoint when the admin is ready to accept applications.
     *
     * @param  StoreGrantRoundRequest $request — validated input (title, description, amounts, dates)
     * @return JsonResponse                    — the newly created grant round with HTTP 201
     */
    public function store(StoreGrantRoundRequest $request): JsonResponse
    {
        // Role check — only admins can create grant rounds
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can create grant rounds.',
                ],
            ], 403);
        }

        // If the round is being created as already-published, record the timestamp.
        // Normally rounds start as drafts and are published later via update(),
        // but an admin might want to create and publish in one step.
        $publishedAt = $request->boolean('is_published') ? now() : null;

        // If a cover image file was uploaded, store it in Supabase Storage via Laravel's S3 driver
        // and get back the public URL. If no file was uploaded, cover_image_url stays null.
        $coverImageUrl = null;
        if ($request->hasFile('cover_image')) {
            try {
                $file     = $request->file('cover_image');
                // uniqid() generates a unique string based on the current time — avoids filename collisions
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();

                // putFileAs() uploads the file to the S3 bucket at 'cover-images/{filename}'
                // and returns the stored path (e.g. "cover-images/abc123.jpg"), or false on failure.
                $path = Storage::disk('s3')->putFileAs('cover-images', $file, $filename);

                if ($path === false) {
                    throw new \Exception('Storage::putFileAs returned false');
                }

                // url() builds the full public URL from AWS_URL + the stored path
                $coverImageUrl = Storage::disk('s3')->url($path);
            } catch (\Exception $e) {
                // Log the real error so we can debug S3 credential/config issues
                Log::error('Cover image upload failed: ' . $e->getMessage());
                return response()->json([
                    'error' => [
                        'code'    => 'storage_upload_failed',
                        'message' => 'Could not upload cover image. Please try again.',
                        // Include the raw error in debug mode so it's visible during development
                        'debug'   => config('app.debug') ? $e->getMessage() : null,
                    ],
                ], 500);
            }
        }

        // Create the round with all provided fields.
        // status always starts as 'draft' regardless of what is sent —
        // publishing is a separate deliberate action via the update endpoint.
        // created_by records which admin created this round.
        $grantRound = GrantRound::create([
            // Core details
            'title'             => $request->title,
            'short_description' => $request->short_description,
            'description'       => $request->description,
            'cover_image_url'   => $coverImageUrl,

            // Eligibility
            'eligible_organisation_types' => $request->eligible_organisation_types,
            'geographic_restrictions'     => $request->geographic_restrictions,
            'eligibility_criteria'        => $request->eligibility_criteria,

            // Application requirements
            'required_documents'      => $request->required_documents,
            'assessment_criteria'     => $request->assessment_criteria,
            'key_focus_areas'         => $request->key_focus_areas,
            'application_form_schema' => $request->application_form_schema,

            // Funding
            'min_funding_amount' => $request->min_funding_amount,
            'max_funding_amount' => $request->max_funding_amount,
            'total_funding_pool' => $request->total_funding_pool,

            // Status & visibility — status always starts as 'draft'
            'status'                      => 'draft',
            'is_published'                => $request->boolean('is_published', false),
            'is_featured'                 => $request->boolean('is_featured', false),
            'allow_multiple_applications' => $request->boolean('allow_multiple_applications', false),
            'max_applications_per_user'   => $request->max_applications_per_user,

            // Schedule
            'opens_at'                => $request->opens_at,
            'closes_at'               => $request->closes_at,
            'assessment_period_start' => $request->assessment_period_start,
            'notification_date'       => $request->notification_date,
            'funding_release_date'    => $request->funding_release_date,
            'published_at'            => $publishedAt,

            // Contact
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,

            // Ownership
            'created_by' => $request->user()->id,
        ]);

        // Load the creator so the response includes their name
        $grantRound->load('creator');

        // 201 Created — the standard HTTP status for a successfully created resource
        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/grant-rounds/{id}
     *
     * Updates an existing grant round (admin only).
     * All fields are optional — only the fields you send are changed (PATCH semantics).
     *
     * Admins can freely set status to any valid value (draft, open, closed, completed).
     * No transition restrictions are enforced — it is admin-only access.
     *
     * @param  UpdateGrantRoundRequest $request    — validated input (any subset of fields)
     * @param  GrantRound              $grantRound — the round to update, resolved from {id}
     * @return JsonResponse                        — the updated grant round, or 422 for invalid transitions
     */
    public function update(UpdateGrantRoundRequest $request, GrantRound $grantRound): JsonResponse
    {
        // Role check — only admins can edit grant rounds
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can update grant rounds.',
                ],
            ], 403);
        }

        // Admins can freely change status to any valid value — no transition restrictions.
        // The valid values are still enforced by UpdateGrantRoundRequest (draft, open, closed, completed).

        // Build the update payload — start with all the fields the admin can freely change.
        // only() picks the named keys from the request and skips anything not sent.
        // Note: cover_image_url is NOT in this list — it's handled separately below via file upload.
        $data = $request->only([
            'title', 'short_description', 'description',
            'eligible_organisation_types', 'geographic_restrictions', 'eligibility_criteria',
            'required_documents', 'assessment_criteria', 'key_focus_areas', 'application_form_schema',
            'min_funding_amount', 'max_funding_amount', 'total_funding_pool',
            'status', 'is_published', 'is_featured', 'allow_multiple_applications', 'max_applications_per_user',
            'opens_at', 'closes_at', 'assessment_period_start', 'notification_date', 'funding_release_date',
            'contact_email', 'contact_phone',
        ]);

        // If a new cover image file was uploaded, store it and update the URL.
        // If no file was sent, cover_image_url is left out of $data so the existing value stays.
        if ($request->hasFile('cover_image')) {
            try {
                $file     = $request->file('cover_image');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path     = Storage::disk('s3')->putFileAs('cover-images', $file, $filename);

                if ($path === false) {
                    throw new \Exception('Storage::putFileAs returned false');
                }

                $data['cover_image_url'] = Storage::disk('s3')->url($path);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => [
                        'code'    => 'storage_upload_failed',
                        'message' => 'Could not upload cover image. Please try again.',
                    ],
                ], 500);
            }
        }

        // Auto-set published_at the first time is_published is switched to true.
        // We only set it once — if it's already set we leave it so the original publish
        // date is preserved even if the round is unpublished and republished later.
        if ($request->has('is_published') && $request->boolean('is_published') && ! $grantRound->published_at) {
            $data['published_at'] = now();
        }

        // Auto-set closed_at the first time status transitions to 'closed' or 'completed'.
        // Same logic — preserve the original close date if already set.
        if ($request->has('status')
            && in_array($request->status, ['closed', 'completed'])
            && ! $grantRound->closed_at
        ) {
            $data['closed_at'] = now();
        }

        // Record which admin made this change
        $data['updated_by'] = $request->user()->id;

        $grantRound->update($data);

        // Reload relationships so the response has up-to-date data
        $grantRound->load('creator');
        $grantRound->loadCount('applications');

        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ]);
    }

    /**
     * DELETE /api/v1/grant-rounds/{id}
     *
     * Deletes a grant round (admin only).
     * A round that has any applications attached to it cannot be deleted —
     * the admin should close it instead. This protects applicant data from
     * being wiped by an accidental delete.
     *
     * @param  Request    $request    — the incoming HTTP request (to get the authenticated user)
     * @param  GrantRound $grantRound — the round to delete, resolved from {id}
     * @return JsonResponse           — 204 No Content on success, 422 if the round has applications
     */
    public function destroy(Request $request, GrantRound $grantRound): JsonResponse
    {
        // Role check — only admins can delete grant rounds
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can delete grant rounds.',
                ],
            ], 403);
        }

        // Check if any applications have been started for this round.
        // exists() is more efficient than count() — it stops as soon as it finds one row.
        if ($grantRound->applications()->exists()) {
            return response()->json([
                'error' => [
                    'code'    => 'has_applications',
                    'message' => 'This grant round has applications and cannot be deleted. Close the round instead.',
                ],
            ], 422);
        }

        $grantRound->delete();

        // 204 No Content — the standard HTTP response for a successful delete with no body
        return response()->json(null, 204);
    }
}
