<?php

namespace App\Enums;

enum OrderNotificationType: string
{
    case Created = 'created';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Pedido creado',
            self::Cancelled => 'Pedido cancelado',
            self::Expired => 'Pedido vencido',
        };
    }
}
