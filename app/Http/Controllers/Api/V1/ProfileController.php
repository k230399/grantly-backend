<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->toArray($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        // validated() returns only fields that were sent and passed, so unsent fields stay unchanged.
        $user->fill($request->validated())->save();

        return response()->json([
            'data' => $this->toArray($user->fresh()),
        ]);
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
