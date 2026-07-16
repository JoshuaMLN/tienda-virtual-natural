<?php

namespace App\Enums;

enum OrderHistoryDomain: string
{
    case Order = 'order';
    case Payment = 'payment';
    case Delivery = 'delivery';
    case Reservation = 'reservation';

    public function label(): string
    {
        return match ($this) {
            self::Order => 'Pedido',
            self::Payment => 'Pago',
            self::Delivery => 'Entrega',
            self::Reservation => 'Reserva',
        };
    }
}
