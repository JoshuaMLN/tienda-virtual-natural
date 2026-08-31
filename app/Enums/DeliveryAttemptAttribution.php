<?php

namespace App\Enums;

enum DeliveryAttemptAttribution: string
{
    case Customer = 'customer';
    case Store = 'store';
    case Carrier = 'carrier';
    case Unattributed = 'unattributed';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Store => 'Tienda',
            self::Carrier => 'Transportista',
            self::Unattributed => 'No atribuible',
        };
    }

    public function consumesAttempt(): bool
    {
        return $this === self::Customer;
    }
}
