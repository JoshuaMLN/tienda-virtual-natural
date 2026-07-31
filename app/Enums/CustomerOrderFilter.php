<?php

namespace App\Enums;

enum CustomerOrderFilter: string
{
    case All = 'all';
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Fulfillment = 'fulfillment';
    case Completed = 'completed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos los estados',
            self::Pending => 'Pendientes',
            self::Preparing => 'Confirmados o en preparacion',
            self::Fulfillment => 'En camino o recojo',
            self::Completed => 'Finalizados',
            self::Closed => 'Cancelados o vencidos',
        };
    }
}
