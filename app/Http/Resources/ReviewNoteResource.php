<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'application_id' => $this->application_id,
            'reviewer_id'    => $this->reviewer_id,
            'note_content'   => $this->note_content,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,

            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id'        => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
                'email'     => $this->reviewer->email,
            ]),
        ];
    }
}
