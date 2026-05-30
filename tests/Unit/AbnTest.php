<?php

namespace Tests\Unit;

use App\Support\Abn;
use PHPUnit\Framework\TestCase;

class AbnTest extends TestCase
{
    public function test_normalise_strips_whitespace(): void
    {
        $this->assertSame('51824753556', Abn::normalise('51 824 753 556'));
    }

    public function test_format_requires_exactly_eleven_digits(): void
    {
        $this->assertTrue(Abn::hasValidFormat('51824753556'));
        $this->assertFalse(Abn::hasValidFormat('5182475355'));   // 10 digits
        $this->assertFalse(Abn::hasValidFormat('518247535566')); // 12 digits
        $this->assertFalse(Abn::hasValidFormat('5182475355a'));  // non-digit
    }

    public function test_checksum_accepts_real_abns(): void
    {
        // Australian Taxation Office + a couple of other publicly registered ABNs.
        $this->assertTrue(Abn::hasValidChecksum('51824753556'));
        $this->assertTrue(Abn::hasValidChecksum('53004085616'));
    }

    public function test_checksum_rejects_typos(): void
    {
        // A single transposed/changed digit must fail the modulus-89 check.
        $this->assertFalse(Abn::hasValidChecksum('51824753557'));
        $this->assertFalse(Abn::hasValidChecksum('11111111111'));
    }

    public function test_is_valid_combines_format_and_checksum(): void
    {
        $this->assertTrue(Abn::isValid('51824753556'));
        $this->assertFalse(Abn::isValid('5182475355'));  // too short
        $this->assertFalse(Abn::isValid('51824753557')); // bad checksum
    }
}
