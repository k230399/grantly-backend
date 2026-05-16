<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

class UpdateApplicationStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:draft,submitted,under_review,approved,rejected'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }
}
