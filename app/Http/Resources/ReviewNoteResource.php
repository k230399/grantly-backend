<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a ReviewNote model into a consistent JSON payload.
 * Review notes are admin-only, so reviewer info is always included when loaded.
 */
class ReviewNoteResource extends JsonResource
{
    /**
     * @param  Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'application_id' => $this->application_id,
            'reviewer_id'    => $this->reviewer_id,
            'note_content'   => $this->note_content,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,

            // Reviewer info — only included when relation is eager-loaded
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id'        => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
                'email'     => $this->reviewer->email,
            ]),
        ];
    }
}
