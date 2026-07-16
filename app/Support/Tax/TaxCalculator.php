<?php

namespace App\Support\Tax;

use App\Enums\TaxAffectation;
use InvalidArgumentException;

final class TaxCalculator
{
    public function fromTaxIncluded(
        int $grossCents,
        TaxAffectation $affectation,
        int $discountCents = 0,
        ?int $rateBasisPoints = null,
    ): TaxBreakdown {
        if ($grossCents < 0 || $discountCents < 0 || $discountCents > $grossCents) {
            throw new InvalidArgumentException('Los importes tributarios no son validos.');
        }

        $rateBasisPoints ??= $affectation->taxRateBasisPoints();
        $totalCents = $grossCents - $discountCents;

        if ($affectation !== TaxAffectation::Taxed) {
            $rateBasisPoints = 0;
        }

        if ($affectation === TaxAffectation::Taxed && ($rateBasisPoints <= 0 || $rateBasisPoints > 10_000)) {
            throw new InvalidArgumentException('Un importe gravado requiere una tasa mayor que 0 y no mayor que 100 %.');
        }

        $taxableValueCents = 0;
        $exemptValueCents = 0;
        $unaffectedValueCents = 0;
        $taxCents = 0;

        if ($affectation === TaxAffectation::Taxed) {
            $taxableValueCents = $this->roundRatio(
                $totalCents * 10_000,
                10_000 + $rateBasisPoints,
            );
            $taxCents = $totalCents - $taxableValueCents;
        } elseif ($affectation === TaxAffectation::Exempt) {
            $exemptValueCents = $totalCents;
        } else {
            $unaffectedValueCents = $totalCents;
        }

        return new TaxBreakdown(
            affectation: $affectation,
            rateBasisPoints: $rateBasisPoints,
            grossCents: $grossCents,
            discountCents: $discountCents,
            taxableValueCents: $taxableValueCents,
            exemptValueCents: $exemptValueCents,
            unaffectedValueCents: $unaffectedValueCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
        );
    }

    private function roundRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
