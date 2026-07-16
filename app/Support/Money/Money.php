<?php

namespace App\Support\Money;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        public int $cents,
    ) {
        if ($this->cents < 0) {
            throw new InvalidArgumentException('El importe monetario no puede ser negativo.');
        }
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimal(int|float|string $amount): self
    {
        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('El importe monetario no es valido.');
            }

            $amount = number_format($amount, 2, '.', '');
        }

        $normalized = trim((string) $amount);

        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('El importe monetario no es valido.');
        }

        $whole = (int) $matches[1];
        $fraction = $matches[2] ?? '';
        $hundredths = (int) str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ($whole * 100) + $hundredths;

        if (isset($fraction[2]) && (int) $fraction[2] >= 5) {
            $cents++;
        }

        return new self($cents);
    }

    public function decimal(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function formatted(string $currency = 'S/ '): string
    {
        $whole = number_format(intdiv($this->cents, 100), 0, '.', ',');
        $fraction = str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);

        return $currency.$whole.'.'.$fraction;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        if ($other->cents > $this->cents) {
            throw new InvalidArgumentException('El resultado monetario no puede ser negativo.');
        }

        return new self($this->cents - $other->cents);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('El multiplicador no puede ser negativo.');
        }

        return new self($this->cents * $quantity);
    }
}
