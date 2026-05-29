<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

// All fields use 'sometimes' so drafts can be saved with partial data (PATCH semantics).
// Ownership and draft-status checks live in the controller.
class UpdateApplicationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ABN is format-checked only; a valid ABN is enforced at submit for organisations.
            'applicant_type'    => ['sometimes', 'in:individual,organisation'],
            'abn'               => ['sometimes', 'nullable', 'string', 'regex:/^\d{11}$/'],
            'organisation_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'project_name'        => ['sometimes', 'required', 'string', 'max:255'],
            'project_description' => ['sometimes', 'required', 'string'],

            'funding_requested'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'total_project_budget' => ['sometimes', 'required', 'numeric', 'min:0', 'gte:funding_requested'],

            'declaration_accepted' => ['sometimes', 'boolean'],

            'form_data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'abn.regex'                => 'ABN must be exactly 11 digits.',
            'total_project_budget.gte' => 'The total project budget must be greater than or equal to the funding requested.',
        ];
    }
}
