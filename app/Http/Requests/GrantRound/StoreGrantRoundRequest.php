<?php

namespace App\Http\Requests\GrantRound;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the incoming request body when creating a new grant round.
 * Only admins can create grant rounds, but the role check happens in the controller
 * so we can return a consistent error shape.
 */
class StoreGrantRoundRequest extends ApiFormRequest
{
    /**
     * Allow any authenticated user to reach the validation layer.
     * The admin-only enforcement is handled in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Runs before validation. When the frontend submits a cover image, the whole
     * request comes in as multipart/form-data — and multipart can only carry strings.
     * The form builder serialises application_form_schema as JSON so it can ride along
     * in that body, and we decode it back into an array here so the 'array' validation
     * rule sees the correct type.
     */
    protected function prepareForValidation(): void
    {
        $schema = $this->input('application_form_schema');
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            // If decoding succeeded, swap in the parsed array; otherwise leave the
            // raw string in place so the 'array' validation rule rejects it cleanly.
            if (is_array($decoded)) {
                $this->merge(['application_form_schema' => $decoded]);
            }
        }
    }

    /**
     * Validation rules for each field.
     * title, description, max_funding_amount, and eligibility_criteria are required
     * because the database columns are NOT NULL.
     * Everything else is optional — rounds can be created as minimal drafts.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // ── Core details ───────────────────────────────────────────────

            // Full name shown as the round's heading
            'title'             => ['required', 'string', 'max:255'],

            // Short marketing blurb shown on listing cards (max 200 chars)
            'short_description' => ['nullable', 'string', 'max:200'],

            // Long-form description of what the round funds
            'description'       => ['required', 'string'],

            // Optional cover image file upload (JPG or PNG, max 5 MB).
            // The controller uploads this to Supabase Storage and saves the resulting URL.
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],

            // ── Eligibility ────────────────────────────────────────────────

            // Free-text description of which organisation types can apply
            'eligible_organisation_types' => ['nullable', 'string'],

            // Any geographic limitations (e.g. "Queensland only")
            'geographic_restrictions'     => ['nullable', 'string'],

            // Detailed eligibility rules
            'eligibility_criteria'        => ['required', 'string'],

            // ── Application requirements ───────────────────────────────────

            // Array of document names applicants must submit
            // 'array' checks the value is a JSON array; '.*' validates each item inside
            'required_documents'    => ['nullable', 'array'],
            'required_documents.*'  => ['string'],

            // How submitted applications will be scored/judged
            'assessment_criteria'   => ['nullable', 'string'],

            // Tags for the themes this round covers (e.g. ["Education", "Health"])
            'key_focus_areas'       => ['nullable', 'array'],
            'key_focus_areas.*'     => ['string'],

            // JSON object defining custom form fields for this round (advanced feature)
            'application_form_schema' => ['nullable', 'array'],

            // ── Funding amounts ────────────────────────────────────────────

            // Minimum an applicant may request (null = no minimum)
            'min_funding_amount' => ['nullable', 'numeric', 'min:0'],

            // Maximum an applicant may request
            // gte:min_funding_amount ensures max is never less than min when both are provided
            'max_funding_amount' => ['required', 'numeric', 'min:0', 'gte:min_funding_amount'],

            // Total pot available across all applications for this round
            'total_funding_pool' => ['nullable', 'numeric', 'min:0'],

            // ── Visibility & settings ──────────────────────────────────────

            // Whether the round appears on the public portal (false = draft/hidden)
            'is_published'  => ['nullable', 'boolean'],

            // Whether the round is featured/highlighted on the homepage
            'is_featured'   => ['nullable', 'boolean'],

            // Whether one user can submit more than one application
            'allow_multiple_applications' => ['nullable', 'boolean'],

            // Hard cap on applications per user (null = no limit)
            'max_applications_per_user'   => ['nullable', 'integer', 'min:1'],

            // ── Schedule ───────────────────────────────────────────────────

            'opens_at'                => ['nullable', 'date'],
            // closes_at must come after opens_at when both are provided
            'closes_at'               => ['nullable', 'date', 'after:opens_at'],
            'assessment_period_start' => ['nullable', 'date'],
            'notification_date'       => ['nullable', 'date'],
            'funding_release_date'    => ['nullable', 'date'],

            // ── Contact ────────────────────────────────────────────────────

            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Human-readable error messages that override Laravel's defaults.
     * These appear in the API response under the 'details' key.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closes_at.after'          => 'The closing date must be after the opening date.',
            'max_funding_amount.gte'   => 'The maximum funding amount must be greater than or equal to the minimum.',
        ];
    }
}
