<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Preparing => 'Preparando',
            self::Shipped => 'Enviado',
            self::ReadyForPickup => 'Listo para recoger',
            self::Delivered => 'Entregado',
            self::PickedUp => 'Recogido',
            self::Cancelled => 'Cancelado',
        };
    }
}
