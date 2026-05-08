<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

// Validates an admin's PATCH to /applications/{id}/status.
// Status is free-form (admin can set any of the five lifecycle states); notes are optional.
class UpdateApplicationStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:draft,submitted,under_review,approved,rejected'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
