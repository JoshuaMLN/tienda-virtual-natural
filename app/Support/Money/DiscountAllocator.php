<?php

namespace App\Support\Money;

use InvalidArgumentException;

final class DiscountAllocator
{
    /**
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    public function allocate(int $discountCents, array $weights): array
    {
        if ($discountCents < 0 || collect($weights)->contains(fn (int $weight): bool => $weight < 0)) {
            throw new InvalidArgumentException('Los importes para distribuir no pueden ser negativos.');
        }

        $totalWeight = array_sum($weights);

        if ($discountCents > $totalWeight) {
            throw new InvalidArgumentException('El descuento no puede superar el subtotal.');
        }

        if ($weights === [] || $discountCents === 0) {
            return array_map(fn (): int => 0, $weights);
        }

        if ($totalWeight === 0) {
            throw new InvalidArgumentException('No se puede distribuir un descuento sin subtotal.');
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;
        $position = 0;

        foreach ($weights as $key => $weight) {
            $numerator = $discountCents * $weight;
            $share = intdiv($numerator, $totalWeight);
            $allocations[$key] = $share;
            $allocated += $share;
            $remainders[] = [
                'key' => $key,
                'remainder' => $numerator % $totalWeight,
                'position' => $position++,
            ];
        }

        usort($remainders, function (array $left, array $right): int {
            $byRemainder = $right['remainder'] <=> $left['remainder'];

            return $byRemainder !== 0
                ? $byRemainder
                : $left['position'] <=> $right['position'];
        });

        for ($remaining = $discountCents - $allocated, $index = 0; $remaining > 0; $remaining--, $index++) {
            $allocations[$remainders[$index]['key']]++;
        }

        return $allocations;
    }
}
