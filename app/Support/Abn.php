<?php

namespace App\Support;

// Single source of truth for ABN shape + validity. Both the ValidAbn request rule
// and AbrLookupService lean on this so the format and checksum logic only lives once.
class Abn
{
    // Position weights from the official ABR check-digit algorithm.
    private const WEIGHTS = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];

    // Strips spaces so "51 824 753 556" and "51824753556" are treated the same.
    public static function normalise(string $abn): string
    {
        return preg_replace('/\s+/', '', $abn);
    }

    public static function hasValidFormat(string $abn): bool
    {
        return (bool) preg_match('/^\d{11}$/', $abn);
    }

    // Official ABR modulus-89 checksum: subtract 1 from the first digit, take the
    // weighted sum across all 11 digits, and a valid ABN divides evenly by 89.
    // Assumes the input has already passed hasValidFormat().
    public static function hasValidChecksum(string $abn): bool
    {
        $sum = 0;
        foreach (self::WEIGHTS as $i => $weight) {
            $digit = (int) $abn[$i] - ($i === 0 ? 1 : 0);
            $sum += $digit * $weight;
        }

        return $sum % 89 === 0;
    }

    // Convenience: both format and checksum must hold.
    public static function isValid(string $abn): bool
    {
        return self::hasValidFormat($abn) && self::hasValidChecksum($abn);
    }
}
