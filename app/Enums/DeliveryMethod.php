<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case HomeDelivery = 'home_delivery';
    case Pickup = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::HomeDelivery => 'Entrega a domicilio',
            self::Pickup => 'Recojo en tienda',
        };
    }
}
