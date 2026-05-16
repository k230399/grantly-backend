<?php

namespace App\Http\Requests\GrantRound;

use App\Http\Requests\ApiFormRequest;

// All fields use 'sometimes' so admins can update a single field without resending the whole record.
class UpdateGrantRoundRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Multipart bodies only carry strings, so the frontend JSON-stringifies application_form_schema.
    protected function prepareForValidation(): void
    {
        $schema = $this->input('application_form_schema');
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (is_array($decoded)) {
                $this->merge(['application_form_schema' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title'             => ['sometimes', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:200'],
            'description'       => ['sometimes', 'string'],
            'cover_image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],

            'eligible_organisation_types' => ['nullable', 'string'],
            'geographic_restrictions'     => ['nullable', 'string'],
            'eligibility_criteria'        => ['sometimes', 'string'],

            'required_documents'      => ['nullable', 'array'],
            'required_documents.*'    => ['string'],
            'assessment_criteria'     => ['nullable', 'string'],
            'key_focus_areas'         => ['nullable', 'array'],
            'key_focus_areas.*'       => ['string'],
            'application_form_schema' => ['nullable', 'array'],

            'min_funding_amount' => ['nullable', 'numeric', 'min:0'],
            'max_funding_amount' => ['sometimes', 'numeric', 'min:0', 'gte:min_funding_amount'],
            'total_funding_pool' => ['nullable', 'numeric', 'min:0'],

            'status'        => ['sometimes', 'string', 'in:draft,open,closed,completed'],
            'is_published'  => ['nullable', 'boolean'],
            'is_featured'   => ['nullable', 'boolean'],

            'allow_multiple_applications' => ['nullable', 'boolean'],
            'max_applications_per_user'   => ['nullable', 'integer', 'min:1'],

            'opens_at'                => ['nullable', 'date'],
            'closes_at'               => ['nullable', 'date', 'after:opens_at'],
            'assessment_period_start' => ['nullable', 'date'],
            'notification_date'       => ['nullable', 'date'],
            'funding_release_date'    => ['nullable', 'date'],

            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in'              => 'Status must be one of: draft, open, closed, completed.',
            'closes_at.after'        => 'The closing date must be after the opening date.',
            'max_funding_amount.gte' => 'The maximum funding amount must be greater than or equal to the minimum.',
        ];
    }
}
