<?php

namespace App\Http\Requests\GrantRound;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the incoming request body when updating an existing grant round.
 * All fields are optional (PATCH semantics) — only the fields you send are updated.
 * Status transition rules (draft→open→closed) are enforced in the controller
 * because they depend on the record's current state, not just the incoming value.
 */
class UpdateGrantRoundRequest extends ApiFormRequest
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
     * Validation rules — all fields use 'sometimes' so admins can update
     * a single field without resending the entire record.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // ── Core details ───────────────────────────────────────────────

            'title'             => ['sometimes', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:200'],
            'description'       => ['sometimes', 'string'],
            // Optional replacement cover image (JPG or PNG, max 5 MB).
            // If provided, the controller uploads it and overwrites the existing cover_image_url.
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],

            // ── Eligibility ────────────────────────────────────────────────

            'eligible_organisation_types' => ['nullable', 'string'],
            'geographic_restrictions'     => ['nullable', 'string'],
            'eligibility_criteria'        => ['sometimes', 'string'],

            // ── Application requirements ───────────────────────────────────

            'required_documents'      => ['nullable', 'array'],
            'required_documents.*'    => ['string'],
            'assessment_criteria'     => ['nullable', 'string'],
            'key_focus_areas'         => ['nullable', 'array'],
            'key_focus_areas.*'       => ['string'],
            'application_form_schema' => ['nullable', 'array'],

            // ── Funding amounts ────────────────────────────────────────────

            'min_funding_amount' => ['nullable', 'numeric', 'min:0'],
            'max_funding_amount' => ['sometimes', 'numeric', 'min:0', 'gte:min_funding_amount'],
            'total_funding_pool' => ['nullable', 'numeric', 'min:0'],

            // ── Status & visibility ────────────────────────────────────────

            // 'completed' added alongside the original three values
            'status'        => ['sometimes', 'string', 'in:draft,open,closed,completed'],
            'is_published'  => ['nullable', 'boolean'],
            'is_featured'   => ['nullable', 'boolean'],

            'allow_multiple_applications' => ['nullable', 'boolean'],
            'max_applications_per_user'   => ['nullable', 'integer', 'min:1'],

            // ── Schedule ───────────────────────────────────────────────────

            'opens_at'                => ['nullable', 'date'],
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
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in'              => 'Status must be one of: draft, open, closed, completed.',
            'closes_at.after'        => 'The closing date must be after the opening date.',
            'max_funding_amount.gte' => 'The maximum funding amount must be greater than or equal to the minimum.',
        ];
    }
}
