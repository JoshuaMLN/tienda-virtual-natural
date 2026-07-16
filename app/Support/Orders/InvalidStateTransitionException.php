<?php

namespace App\Support\Orders;

use DomainException;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $domain,
        public readonly string $from,
        public readonly string $to,
        ?string $message = null,
    ) {
        parent::__construct($message ?? "La transicion de {$domain} de {$from} a {$to} no esta permitida.");
    }
}
