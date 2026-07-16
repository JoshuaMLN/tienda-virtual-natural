<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Processing => 'Procesando',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Vencido',
        };
    }
}
