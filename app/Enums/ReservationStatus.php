<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Active = 'active';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Consumed => 'Consumida',
            self::Released => 'Liberada',
            self::Expired => 'Vencida',
        };
    }
}
