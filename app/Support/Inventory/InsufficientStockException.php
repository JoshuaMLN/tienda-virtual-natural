<?php

namespace App\Support\Inventory;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $requestedQuantity,
        public readonly int $availableStock,
    ) {
        parent::__construct('No hay stock suficiente para completar la operacion.');
    }
}
