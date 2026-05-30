<?php

namespace App\Rules;

use App\Support\Abn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Validates that an ABN is both well-formed (11 digits) and passes the official
// ABR modulus-89 checksum, catching typos and made-up numbers before we ever
// call the register. Pair with 'nullable' so a cleared ABN is skipped.
class ValidAbn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $abn = Abn::normalise((string) $value);

        if (! Abn::hasValidFormat($abn)) {
            $fail('ABN must be exactly 11 digits.');

            return;
        }

        if (! Abn::hasValidChecksum($abn)) {
            $fail('This ABN is not valid. Please check for typos.');
        }
    }
}
