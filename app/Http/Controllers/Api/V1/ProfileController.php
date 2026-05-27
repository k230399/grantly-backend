<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\AbrLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProfileController extends Controller
{
    // GET /api/v1/profile
    // Returns the authenticated user's full profile. Email and role are exposed read-only;
    // changes to those go through Supabase Auth and the admin workflow respectively.
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->toArray($request->user()),
        ]);
    }

    // PATCH /api/v1/profile
    // Updates the editable fields on the user's own profile. Whenever the ABN changes, the
    // controller re-verifies it via the ABR (server-side, so the UI cannot be bypassed) and
    // rejects cancelled or unknown ABNs with a 422 invalid_abn.
    public function update(UpdateProfileRequest $request, AbrLookupService $abr): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Re-verify ABN against the ABR whenever the caller changes it, so the rule cannot be
        // bypassed by hitting the API directly. Cached lookups make this near-free when the
        // frontend has already validated the same ABN in this hour.
        if (array_key_exists('abn', $data) && $data['abn'] !== null && $data['abn'] !== $user->abn) {
            $error = $this->verifyAbn($abr, $data['abn']);
            if ($error !== null) {
                return $error;
            }
        }

        // validated() returns only fields that were sent and passed, so unsent fields stay unchanged.
        $user->fill($data)->save();

        return response()->json([
            'data' => $this->toArray($user->fresh()),
        ]);
    }

    // Returns a JsonResponse to bail with, or null when the ABN is valid + active.
    private function verifyAbn(AbrLookupService $abr, string $abn): ?JsonResponse
    {
        try {
            $abr->lookup($abn);
            return null;
        } catch (RuntimeException $e) {
            $code = $e->getMessage();

            // Service-misconfig is treated as a soft pass: the format check already ran and we do not
            // want to block applicants from saving their profile just because the ABR key is missing.
            if ($code === 'not_configured') {
                return null;
            }

            $message = match ($code) {
                'not_found' => 'This ABN was not found on the Australian Business Register.',
                'cancelled' => 'This ABN is on the register but is no longer active.',
                'invalid_format' => 'ABN must be exactly 11 digits.',
                default => 'Could not verify this ABN against the Australian Business Register. Please try again.',
            };

            return response()->json([
                'error' => [
                    'code'    => 'invalid_abn',
                    'message' => $message,
                    'details' => ['abn' => [$message]],
                ],
            ], 422);
        }
    }

    private function toArray($user): array
    {
        return [
            'id'                => $user->id,
            'email'             => $user->email,
            'full_name'         => $user->full_name,
            'organisation_name' => $user->organisation_name,
            'abn'               => $user->abn,
            'phone'             => $user->phone,
            'address'           => $user->address,
            'state'             => $user->state,
            'postcode'          => $user->postcode,
            'role'              => $user->role,
            'created_at'        => $user->created_at?->toIso8601String(),
            'updated_at'        => $user->updated_at?->toIso8601String(),
        ];
    }
}
