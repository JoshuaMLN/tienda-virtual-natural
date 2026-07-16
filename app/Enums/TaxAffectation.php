<?php

namespace App\Enums;

enum TaxAffectation: string
{
    case Taxed = 'taxed';
    case Exempt = 'exempt';
    case Unaffected = 'unaffected';

    public function label(): string
    {
        return match ($this) {
            self::Taxed => 'Gravado 18 %',
            self::Exempt => 'Exonerado',
            self::Unaffected => 'Inafecto',
        };
    }

    public function taxRateBasisPoints(): int
    {
        return $this === self::Taxed ? 1800 : 0;
    }

    public function sunatCode(): string
    {
        return match ($this) {
            self::Taxed => '10',
            self::Exempt => '20',
            self::Unaffected => '30',
        };
    }
}
