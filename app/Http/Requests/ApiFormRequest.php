<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base class for all API form requests in this project.
 *
 * By default, Laravel returns validation errors in its own format:
 *   { "message": "...", "errors": { "field": ["..."] } }
 *
 * But our API has a consistent error format:
 *   { "error": { "code": "validation_error", "message": "...", "details": { ... } } }
 *
 * Every form request class extends this instead of Laravel's FormRequest directly
 * so that all validation errors automatically use the right shape.
 */
abstract class ApiFormRequest extends FormRequest
{
    /**
     * Called by Laravel when validation fails.
     * Instead of letting Laravel return its default format, we throw an HTTP exception
     * with our custom error shape so the frontend always gets a consistent response.
     *
     * @param  Validator $validator — contains the failed rules and their error messages
     * @throws HttpResponseException — interrupts the request and returns the JSON error immediately
     */
    protected function failedValidation(Validator $validator): void
    {
        // errors() returns an array like: ["field" => ["error message 1", ...], ...]
        $errors = $validator->errors()->toArray();

        // Use the first error message across all fields as the top-level message.
        // array_values()[0] gets the first field's errors; [0] gets the first message for that field.
        $firstMessage = array_values($errors)[0][0] ?? 'Validation failed.';

        throw new HttpResponseException(
            response()->json([
                'error' => [
                    'code'    => 'validation_error',
                    // Human-readable summary — the first failing rule's message
                    'message' => $firstMessage,
                    // Full details so the frontend can highlight specific fields
                    'details' => $errors,
                ],
            ], 422)
        );
    }
}
