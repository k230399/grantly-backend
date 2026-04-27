<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // Allow all users to attempt this request — no auth required to log in.
    public function authorize(): bool
    {
        return true;
    }

    // Define the validation rules for the login form.
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }
}
