<?php

namespace App\Support\Orders;

use InvalidArgumentException;

final readonly class OrderNumber
{
    public string $code;

    public function __construct(
        public int $year,
        public int $number,
    ) {
        if ($this->year < 1000 || $this->year > 9999 || $this->number < 1 || $this->number > 999_999) {
            throw new InvalidArgumentException('El correlativo del pedido esta fuera del rango permitido.');
        }

        $this->code = sprintf('PED-%d-%06d', $this->year, $this->number);
    }
}
