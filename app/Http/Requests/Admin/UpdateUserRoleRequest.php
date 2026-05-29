<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

// Admin-only route; the 'admin' middleware enforces access, so authorize() just passes.
class UpdateUserRoleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'in:admin,applicant'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role must be either admin or applicant.',
        ];
    }
}
