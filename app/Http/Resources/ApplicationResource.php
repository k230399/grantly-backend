<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms an Application model into a consistent JSON shape for API responses.
 * Used by ApplicationController for both single-record (show/store/update/submit)
 * and collection (index) responses.
 */
class ApplicationResource extends JsonResource
{
    /**
     * Convert the Application model into an array that will be serialised as JSON.
     *
     * $this refers to the Application model instance.
     *
     * @param  Request $request — the current HTTP request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ───────────────────────────────────────────────────

            // UUID primary key
            'id' => $this->id,

            // Human-readable display id (e.g. "APP-2026-000042"). The model's $appends
            // already includes this, but we list it explicitly here for clarity.
            'reference_number' => $this->reference_number,

            // ── Foreign keys ───────────────────────────────────────────────

            'applicant_id'   => $this->applicant_id,
            'grant_round_id' => $this->grant_round_id,

            // ── Core project fields ────────────────────────────────────────

            'project_name'        => $this->project_name,
            'project_description' => $this->project_description,

            // Cast money to float so they serialise as numbers (5000.00) not strings ("5000.00").
            // The model's decimal:2 cast keeps two decimals; the (float) here strips the wrapping
            // quotes that decimal: produces.
            'funding_requested'    => $this->funding_requested !== null ? (float) $this->funding_requested : null,
            'total_project_budget' => $this->total_project_budget !== null ? (float) $this->total_project_budget : null,

            // The applicant's confirmation tickbox — true once they've ticked it
            'declaration_accepted' => (bool) $this->declaration_accepted,

            // The applicant's answers to the custom questions defined by the grant round.
            // Already decoded to a PHP array by the model's 'json' cast — passes through as-is.
            // May be null for a freshly created draft.
            'form_data' => $this->form_data,

            // ── Status ─────────────────────────────────────────────────────

            // One of: draft | submitted | under_review | approved | rejected
            'status'       => $this->status,
            // Set automatically when the applicant clicks Submit
            'submitted_at' => $this->submitted_at?->toIso8601String(),

            // ── Optional nested relationships (only included when loaded) ──

            // Grant round summary — only included when load('grantRound') was called.
            // We expose only the fields the frontend actually needs (title, status, schema)
            // rather than the entire round to keep the payload small.
            'grant_round' => $this->whenLoaded('grantRound', fn () => [
                'id'                      => $this->grantRound->id,
                'title'                   => $this->grantRound->title,
                'status'                  => $this->grantRound->status,
                // The custom-question schema, so the frontend can render the form fields
                // and the applicant's answers (form_data) side by side.
                'application_form_schema' => $this->grantRound->application_form_schema,
            ]),

            // Applicant summary — only included when load('applicant') was called.
            // Admins viewing a list of applications need the applicant's name and email
            // for context.
            'applicant' => $this->whenLoaded('applicant', fn () => [
                'id'        => $this->applicant->id,
                'full_name' => $this->applicant->full_name,
                'email'     => $this->applicant->email,
            ]),

            // Document count — only included when withCount('documents') was used
            'documents_count' => $this->whenCounted('documents'),

            // ── Timestamps ────────────────────────────────────────────────

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
