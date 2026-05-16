<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantRoundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'short_description' => $this->short_description,
            'description'       => $this->description,
            'cover_image_url'   => $this->cover_image_url,

            'eligible_organisation_types' => $this->eligible_organisation_types,
            'geographic_restrictions'     => $this->geographic_restrictions,
            'eligibility_criteria'        => $this->eligibility_criteria,

            'required_documents'      => $this->required_documents,
            'assessment_criteria'     => $this->assessment_criteria,
            'key_focus_areas'         => $this->key_focus_areas,
            'application_form_schema' => $this->application_form_schema,

            // Cast money so it serialises as numbers rather than strings.
            'min_funding_amount' => $this->min_funding_amount !== null ? (float) $this->min_funding_amount : null,
            'max_funding_amount' => $this->max_funding_amount !== null ? (float) $this->max_funding_amount : null,
            'total_funding_pool' => $this->total_funding_pool !== null ? (float) $this->total_funding_pool : null,

            'status'       => $this->status,
            'is_published' => (bool) $this->is_published,
            'is_featured'  => (bool) $this->is_featured,

            'allow_multiple_applications' => (bool) $this->allow_multiple_applications,
            'max_applications_per_user'   => $this->max_applications_per_user,

            'opens_at'                => $this->opens_at?->toIso8601String(),
            'closes_at'               => $this->closes_at?->toIso8601String(),
            'assessment_period_start' => $this->assessment_period_start?->toIso8601String(),
            'notification_date'       => $this->notification_date?->toIso8601String(),
            'funding_release_date'    => $this->funding_release_date?->toIso8601String(),
            'published_at'            => $this->published_at?->toIso8601String(),
            'closed_at'               => $this->closed_at?->toIso8601String(),

            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Nested relationships only appear when the controller has eager-loaded them.
            'creator' => $this->whenLoaded('creator', fn () => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),

            'applications_count' => $this->whenCounted('applications'),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
