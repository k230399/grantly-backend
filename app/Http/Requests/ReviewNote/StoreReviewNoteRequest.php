<?php

namespace App\Http\Requests\ReviewNote;

use App\Http\Requests\ApiFormRequest;

// Validates an admin's POST to /applications/{application}/review-notes.
// Review notes are admin-only; only the note body is accepted.
class StoreReviewNoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note_content' => ['required', 'string', 'max:5000'],
        ];
    }
}
