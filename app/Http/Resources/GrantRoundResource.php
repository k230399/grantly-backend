<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a GrantRound model into a consistent JSON shape for API responses.
 * Used by GrantRoundController for both single-record (show/store/update)
 * and collection (index) responses.
 */
class GrantRoundResource extends JsonResource
{
    /**
     * Convert the GrantRound model into an array that will be serialised as JSON.
     *
     * $this refers to the GrantRound model instance.
     *
     * @param  Request $request — the current HTTP request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ───────────────────────────────────────────────────

            // Unique identifier — UUID string (e.g. "018e1234-...")
            'id'     => $this->id,

            // ── Core details ───────────────────────────────────────────────

            'title'             => $this->title,
            // Short blurb for listing cards — may be null if not set
            'short_description' => $this->short_description,
            'description'       => $this->description,
            // Full URL to the cover image, or null if no image uploaded
            'cover_image_url'   => $this->cover_image_url,

            // ── Eligibility ────────────────────────────────────────────────

            'eligible_organisation_types' => $this->eligible_organisation_types,
            'geographic_restrictions'     => $this->geographic_restrictions,
            'eligibility_criteria'        => $this->eligibility_criteria,

            // ── Application requirements ───────────────────────────────────

            // PHP array (e.g. ["Budget", "Annual Report"]), or null
            'required_documents'      => $this->required_documents,
            'assessment_criteria'     => $this->assessment_criteria,
            // PHP array of focus area tags (e.g. ["Education", "Health"]), or null
            'key_focus_areas'         => $this->key_focus_areas,
            // JSON object defining custom form fields, or null
            'application_form_schema' => $this->application_form_schema,

            // ── Funding amounts ────────────────────────────────────────────

            // Cast to float so they serialise as numbers (e.g. 5000.00) not strings
            'min_funding_amount' => $this->min_funding_amount !== null ? (float) $this->min_funding_amount : null,
            'max_funding_amount' => $this->max_funding_amount !== null ? (float) $this->max_funding_amount : null,
            'total_funding_pool' => $this->total_funding_pool !== null ? (float) $this->total_funding_pool : null,

            // ── Status & visibility ────────────────────────────────────────

            // One of: draft | open | closed | completed
            'status'       => $this->status,
            // Whether the round is visible on the public applicant portal
            'is_published' => (bool) $this->is_published,
            // Whether the round is highlighted on the homepage
            'is_featured'  => (bool) $this->is_featured,

            'allow_multiple_applications' => (bool) $this->allow_multiple_applications,
            // Max applications per user — null means no limit
            'max_applications_per_user'   => $this->max_applications_per_user,

            // ── Schedule — ISO 8601 format or null if not set ──────────────

            'opens_at'                => $this->opens_at?->toIso8601String(),
            'closes_at'               => $this->closes_at?->toIso8601String(),
            'assessment_period_start' => $this->assessment_period_start?->toIso8601String(),
            'notification_date'       => $this->notification_date?->toIso8601String(),
            'funding_release_date'    => $this->funding_release_date?->toIso8601String(),
            // Set automatically when is_published is set to true
            'published_at'            => $this->published_at?->toIso8601String(),
            // Set automatically when status transitions to 'closed'
            'closed_at'               => $this->closed_at?->toIso8601String(),

            // ── Contact ────────────────────────────────────────────────────

            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            // ── Ownership ─────────────────────────────────────────────────

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Nested creator — only included when load('creator') was called
            // (whenLoaded avoids triggering a surprise extra database query)
            'creator' => $this->whenLoaded('creator', fn () => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),

            // Total applications on this round — only included when withCount('applications') was used
            'applications_count' => $this->whenCounted('applications'),

            // ── Timestamps ────────────────────────────────────────────────

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
