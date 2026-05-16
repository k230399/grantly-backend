<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

// Base class for all API form requests.
// Overrides Laravel's default validation error shape to match our { error: { code, message, details } } format.
abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        $errors       = $validator->errors()->toArray();
        $firstMessage = array_values($errors)[0][0] ?? 'Validation failed.';

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code'    => 'validation_error',
                    'message' => $firstMessage,
                    'details' => $errors,
                ],
            ], 422)
        );
    }
}
