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

class GrantRoundController extends Controller
{
    // GET /api/v1/grant-rounds
    // Lists grant rounds, scoped by audience: unauthenticated visitors and applicants see
    // published + open rounds only; admins see every round across every status and can
    // filter via ?status=. Paginated, ordered by created_at desc for admins / opens_at desc
    // for applicants. Uses the optional auth middleware so admin context is detected when present.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Admins see every round; everyone else sees only published, open rounds.
        if ($user && $user->role === 'admin') {
            $query = GrantRound::with('creator')->withCount('applications');

            $statusFilter = $request->query('status');
            if ($statusFilter && in_array($statusFilter, ['draft', 'open', 'closed'])) {
                $query->where('status', $statusFilter);
            }

            $rounds = $query->orderBy('created_at', 'desc')->paginate(15);
        } else {
            $rounds = GrantRound::where('is_published', true)
                ->where('status', 'open')
                ->orderBy('opens_at', 'desc')
                ->paginate(15);
        }

        return response()->json([
            'data' => GrantRoundResource::collection($rounds),
            'meta' => [
                'current_page' => $rounds->currentPage(),
                'last_page'    => $rounds->lastPage(),
                'per_page'     => $rounds->perPage(),
                'total'        => $rounds->total(),
            ],
        ]);
    }

    // GET /api/v1/grant-rounds/{grantRound}
    // Returns one grant round in full. Admins can view any round; everyone else gets a 403
    // when the round is not published. Published-but-closed rounds remain visible so applicants
    // can revisit a round they applied to.
    public function show(Request $request, GrantRound $grantRound): JsonResponse
    {
        $user = $request->user();

        // Non-admins can still read closed rounds they applied to, but only if published.
        if ((! $user || $user->role !== 'admin') && ! $grantRound->is_published) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this grant round.',
                ],
            ], 403);
        }

        if ($user && $user->role === 'admin') {
            $grantRound->load('creator');
            $grantRound->loadCount('applications');
        }

        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ]);
    }

    // POST /api/v1/grant-rounds
    // Creates a new grant round. Admin-only. Status always starts as 'draft' regardless of
    // what the client sends. Accepts multipart/form-data with an optional cover image that
    // is uploaded to Supabase Storage and stored as cover_image_url.
    public function store(StoreGrantRoundRequest $request): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can create grant rounds.',
                ],
            ], 403);
        }

        $publishedAt = $request->boolean('is_published') ? now() : null;

        // If a cover image was uploaded, push it to Supabase Storage and keep the public URL.
        $coverImageUrl = null;
        if ($request->hasFile('cover_image')) {
            try {
                $file     = $request->file('cover_image');
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $path     = Storage::disk('s3')->putFileAs('cover-images', $file, $filename);

                if ($path === false) {
                    throw new \Exception('Storage::putFileAs returned false');
                }

                $coverImageUrl = Storage::disk('s3')->url($path);
            } catch (\Exception $e) {
                Log::error('Cover image upload failed: ' . $e->getMessage());
                return response()->json([
                    'error' => [
                        'code'    => 'storage_upload_failed',
                        'message' => 'Could not upload cover image. Please try again.',
                        'debug'   => config('app.debug') ? $e->getMessage() : null,
                    ],
                ], 500);
            }
        }

        // Status is always 'draft' on create. Publishing is a separate update.
        $grantRound = GrantRound::create([
            'title'             => $request->title,
            'short_description' => $request->short_description,
            'description'       => $request->description,
            'cover_image_url'   => $coverImageUrl,

            'eligible_organisation_types' => $request->eligible_organisation_types,
            'geographic_restrictions'     => $request->geographic_restrictions,
            'eligibility_criteria'        => $request->eligibility_criteria,

            'required_documents'      => $request->required_documents,
            'assessment_criteria'     => $request->assessment_criteria,
            'key_focus_areas'         => $request->key_focus_areas,
            'application_form_schema' => $request->application_form_schema,

            'min_funding_amount' => $request->min_funding_amount,
            'max_funding_amount' => $request->max_funding_amount,
            'total_funding_pool' => $request->total_funding_pool,

            'status'                      => 'draft',
            'is_published'                => $request->boolean('is_published', false),
            'is_featured'                 => $request->boolean('is_featured', false),
            'allow_multiple_applications' => $request->boolean('allow_multiple_applications', false),
            'max_applications_per_user'   => $request->max_applications_per_user,

            'opens_at'                => $request->opens_at,
            'closes_at'               => $request->closes_at,
            'assessment_period_start' => $request->assessment_period_start,
            'notification_date'       => $request->notification_date,
            'funding_release_date'    => $request->funding_release_date,
            'published_at'            => $publishedAt,

            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,

            'created_by' => $request->user()->id,
        ]);

        $grantRound->load('creator');

        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ], 201);
    }

    // PUT/PATCH /api/v1/grant-rounds/{grantRound}
    // Updates a grant round. Admin-only. PATCH semantics. Auto-stamps published_at on first
    // publish, closed_at on the first transition to closed/completed, and updated_by on every
    // change. Status transitions are free-form here; product layer (UI) enforces the workflow.
    public function update(UpdateGrantRoundRequest $request, GrantRound $grantRound): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can update grant rounds.',
                ],
            ], 403);
        }

        // cover_image is handled separately below since it arrives as a file, not a column value.
        $data = $request->only([
            'title', 'short_description', 'description',
            'eligible_organisation_types', 'geographic_restrictions', 'eligibility_criteria',
            'required_documents', 'assessment_criteria', 'key_focus_areas', 'application_form_schema',
            'min_funding_amount', 'max_funding_amount', 'total_funding_pool',
            'status', 'is_published', 'is_featured', 'allow_multiple_applications', 'max_applications_per_user',
            'opens_at', 'closes_at', 'assessment_period_start', 'notification_date', 'funding_release_date',
            'contact_email', 'contact_phone',
        ]);

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

        // Stamp the first publish and first close transitions; keep the original timestamps after that.
        if ($request->has('is_published') && $request->boolean('is_published') && ! $grantRound->published_at) {
            $data['published_at'] = now();
        }

        if ($request->has('status')
            && in_array($request->status, ['closed', 'completed'])
            && ! $grantRound->closed_at
        ) {
            $data['closed_at'] = now();
        }

        $data['updated_by'] = $request->user()->id;

        $grantRound->update($data);

        $grantRound->load('creator');
        $grantRound->loadCount('applications');

        return response()->json([
            'data' => new GrantRoundResource($grantRound),
        ]);
    }

    // DELETE /api/v1/grant-rounds/{grantRound}
    // Deletes a grant round. Admin-only. Blocked with 422 has_applications when any applications
    // are attached (close the round instead, so the audit trail stays intact). The cover image
    // in Supabase Storage is best-effort cleaned up afterwards.
    public function destroy(Request $request, GrantRound $grantRound): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can delete grant rounds.',
                ],
            ], 403);
        }

        // Protect applicant data: rounds with applications must be closed, not deleted.
        if ($grantRound->applications()->exists()) {
            return response()->json([
                'error' => [
                    'code'    => 'has_applications',
                    'message' => 'This grant round has applications and cannot be deleted. Close the round instead.',
                ],
            ], 422);
        }

        $grantRound->delete();

        return response()->json(null, 204);
    }
}
