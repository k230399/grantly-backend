<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\ApiFormRequest;

class UpdateProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name'         => ['sometimes', 'required', 'string', 'max:255'],
            'organisation_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            // ABN format check. The controller calls AbrLookupService when the value changes,
            // rejecting cancelled or unknown ABNs with a separate invalid_abn error code.
            'abn'               => ['sometimes', 'nullable', 'string', 'regex:/^\d{11}$/'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:1000'],
            'state'             => ['sometimes', 'nullable', 'string', 'in:NSW,VIC,QLD,SA,WA,TAS,NT,ACT'],
            'postcode'          => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'abn.regex'      => 'ABN must be exactly 11 digits.',
            'state.in'       => 'State must be one of NSW, VIC, QLD, SA, WA, TAS, NT, or ACT.',
            'postcode.regex' => 'Postcode must be exactly 4 digits.',
        ];
    }
}
