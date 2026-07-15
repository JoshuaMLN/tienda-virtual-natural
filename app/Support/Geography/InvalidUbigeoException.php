<?php

namespace App\Support\Geography;

use InvalidArgumentException;

class InvalidUbigeoException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $provinceCode,
        public readonly string $ubigeo,
    ) {
        parent::__construct('La provincia y el distrito no pertenecen al area de cobertura habilitada.');
    }
}
