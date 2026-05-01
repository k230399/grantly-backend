<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates a PATCH/PUT to an existing application.
 *
 * All fields are optional — applicants typically save the form many times as
 * they fill it in, sending only the changed fields each time. The grant_round_id
 * is intentionally not editable here: once an application is created against a
 * round, that link is fixed.
 *
 * Ownership and "is this still in draft status?" checks live in the controller —
 * we only validate the shape of the data here.
 */
class UpdateApplicationRequest extends ApiFormRequest
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
            // 'sometimes' tells Laravel: only apply these rules if the field is actually
            // present in the request body. So a PATCH that only sends project_name
            // doesn't trigger validation on the other fields.
            'project_name'        => ['sometimes', 'required', 'string', 'max:255'],
            'project_description' => ['sometimes', 'required', 'string'],

            // Same as Store: budget must always be >= requested when both are set.
            'funding_requested'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'total_project_budget' => ['sometimes', 'required', 'numeric', 'min:0', 'gte:funding_requested'],

            'declaration_accepted' => ['sometimes', 'boolean'],

            // form_data may be sent as null (e.g. to clear answers) or as a partial object
            'form_data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_project_budget.gte' => 'The total project budget must be greater than or equal to the funding requested.',
        ];
    }
}
