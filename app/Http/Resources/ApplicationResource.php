<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'reference_number' => $this->reference_number,

            'applicant_id'   => $this->applicant_id,
            'grant_round_id' => $this->grant_round_id,

            'project_name'        => $this->project_name,
            'project_description' => $this->project_description,

            // Cast money so it serialises as numbers (5000.00) rather than strings.
            'funding_requested'    => $this->funding_requested !== null ? (float) $this->funding_requested : null,
            'total_project_budget' => $this->total_project_budget !== null ? (float) $this->total_project_budget : null,

            'declaration_accepted' => (bool) $this->declaration_accepted,
            'form_data'            => $this->form_data,

            'status'       => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),

            // Nested relationships only appear when the controller has eager-loaded them.
            'grant_round' => $this->whenLoaded('grantRound', fn () => [
                'id'                      => $this->grantRound->id,
                'title'                   => $this->grantRound->title,
                'status'                  => $this->grantRound->status,
                'application_form_schema' => $this->grantRound->application_form_schema,
            ]),

            'applicant' => $this->whenLoaded('applicant', fn () => [
                'id'        => $this->applicant->id,
                'full_name' => $this->applicant->full_name,
                'email'     => $this->applicant->email,
            ]),

            'documents_count' => $this->whenCounted('documents'),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
