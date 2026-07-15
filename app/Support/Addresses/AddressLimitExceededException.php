<?php

namespace App\Support\Addresses;

use RuntimeException;

class AddressLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("No puedes guardar mas de {$limit} direcciones.");
    }
}
