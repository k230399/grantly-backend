<?php

namespace App\Http\Requests\ReviewNote;

use App\Http\Requests\ApiFormRequest;

class StoreReviewNoteRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note_content' => ['required', 'string', 'max:5000'],
        ];
    }
}
