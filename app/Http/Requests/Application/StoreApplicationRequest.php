<?php

namespace App\Http\Requests\Application;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the incoming request body when an applicant creates a new application.
 *
 * The request must include the grant round being applied to and the core project
 * fields. Custom answers (form_data) are optional at create time — the applicant
 * fills those in by editing the draft. They only become required at submit time.
 *
 * The role check (applicant only — admins cannot create applications) lives in
 * the controller so the error response uses the consistent { error: { code, message } }
 * shape rather than Laravel's authorize() default 403.
 */
class StoreApplicationRequest extends ApiFormRequest
{
    /**
     * Allow any authenticated user past the validation layer.
     * The applicant-only enforcement is handled in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for each field.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Must be a real grant round in the database — exists: checks the row is present
            'grant_round_id' => ['required', 'uuid', 'exists:grant_rounds,id'],

            // Core project fields — required because the underlying database columns are NOT NULL
            'project_name'        => ['required', 'string', 'max:255'],
            'project_description' => ['required', 'string'],

            // Funding amounts — both required and must be sensible numbers.
            // gte:funding_requested means total budget must be at least the funding requested
            // (you can't ask for $5000 to fund a $2000 project).
            'funding_requested'    => ['required', 'numeric', 'min:0'],
            'total_project_budget' => ['required', 'numeric', 'min:0', 'gte:funding_requested'],

            // The "I confirm everything is accurate" tickbox — optional at create time,
            // required at submit time (enforced by the submit endpoint, not here).
            'declaration_accepted' => ['nullable', 'boolean'],

            // Answers to the round's custom questions — free-form JSON object.
            // We don't validate against the round's application_form_schema here because
            // the applicant may save partial answers as they go. Schema validation happens
            // when they hit submit (Step 7's submit endpoint).
            'form_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Human-readable error messages that override Laravel's defaults.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grant_round_id.exists'    => 'The selected grant round does not exist.',
            'total_project_budget.gte' => 'The total project budget must be greater than or equal to the funding requested.',
        ];
    }
}
