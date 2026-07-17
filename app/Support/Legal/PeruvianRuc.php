<?php

namespace App\Support\Legal;

final class PeruvianRuc
{
    /** @var list<int> */
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function isValid(?string $value): bool
    {
        $ruc = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (! preg_match('/^(10|15|16|17|20)\d{9}$/', $ruc)) {
            return false;
        }

        $sum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $sum += ((int) $ruc[$index]) * $weight;
        }

        $expected = 11 - ($sum % 11);
        $expected = match ($expected) {
            10 => 0,
            11 => 1,
            default => $expected,
        };

        return $expected === (int) $ruc[10];
    }
}
