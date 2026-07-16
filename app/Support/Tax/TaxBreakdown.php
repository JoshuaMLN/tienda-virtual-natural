<?php

namespace App\Support\Tax;

use App\Enums\TaxAffectation;

final readonly class TaxBreakdown
{
    public function __construct(
        public TaxAffectation $affectation,
        public int $rateBasisPoints,
        public int $grossCents,
        public int $discountCents,
        public int $taxableValueCents,
        public int $exemptValueCents,
        public int $unaffectedValueCents,
        public int $taxCents,
        public int $totalCents,
    ) {}

    public function netValueCents(): int
    {
        return $this->taxableValueCents + $this->exemptValueCents + $this->unaffectedValueCents;
    }
}
