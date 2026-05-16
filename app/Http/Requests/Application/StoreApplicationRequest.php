<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

// Role check (applicant only) lives in the controller so it returns our standard error shape.
class StoreApplicationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grant_round_id' => ['required', 'uuid', 'exists:grant_rounds,id'],

            'project_name'        => ['required', 'string', 'max:255'],
            'project_description' => ['required', 'string'],

            // Budget must be at least the funding requested: you can't ask for more than the project costs.
            'funding_requested'    => ['required', 'numeric', 'min:0'],
            'total_project_budget' => ['required', 'numeric', 'min:0', 'gte:funding_requested'],

            // Declaration is optional on create; required at submit time.
            'declaration_accepted' => ['nullable', 'boolean'],

            // form_data is not validated against the round's schema here so drafts can save partial answers.
            'form_data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'grant_round_id.exists'    => 'The selected grant round does not exist.',
            'total_project_budget.gte' => 'The total project budget must be greater than or equal to the funding requested.',
        ];
    }
}
