<?php

namespace Tests\Unit\Legal;

use App\Support\Legal\PeruvianRuc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PeruvianRucTest extends TestCase
{
    #[DataProvider('validRucs')]
    public function test_it_accepts_valid_peruvian_rucs(string $ruc): void
    {
        $this->assertTrue(PeruvianRuc::isValid($ruc));
    }

    public static function validRucs(): array
    {
        return [
            'sunat' => ['20131312955'],
            'banco de la nacion' => ['20100030595'],
        ];
    }

    #[DataProvider('invalidRucs')]
    public function test_it_rejects_invalid_rucs(?string $ruc): void
    {
        $this->assertFalse(PeruvianRuc::isValid($ruc));
    }

    public static function invalidRucs(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'short' => ['2013131295'],
            'invalid prefix' => ['30131312955'],
            'invalid checksum' => ['20131312954'],
        ];
    }
}
