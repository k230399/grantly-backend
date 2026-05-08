<?php

namespace App\Http\Requests\ReviewNote;

use App\Http\Requests\ApiFormRequest;

// Validates an admin's PATCH to /review-notes/{note}.
// Only the note body is editable.
class UpdateReviewNoteRequest extends ApiFormRequest
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
