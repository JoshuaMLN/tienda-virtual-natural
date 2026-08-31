<?php

namespace App\Enums;

enum DeliveryAttemptResult: string
{
    case Delivered = 'delivered';
    case Incident = 'incident';

    public function label(): string
    {
        return match ($this) {
            self::Delivered => 'Entregado',
            self::Incident => 'Incidencia',
        };
    }
}
