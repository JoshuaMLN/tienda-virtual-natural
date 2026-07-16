<?php

namespace App\Support\Orders\Reservations;

use DomainException;

class InvalidReservationTransitionException extends DomainException
{
    public function __construct(string $from, string $to, ?string $message = null)
    {
        parent::__construct($message ?? "La reserva no puede cambiar de {$from} a {$to}.");
    }
}
