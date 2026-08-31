<?php

namespace App\Enums;

enum DeliveryTrackingStatus: string
{
    case Active = 'active';
    case AwaitingReshipmentPayment = 'awaiting_reshipment_payment';
    case ManualFollowUp = 'manual_follow_up';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Seguimiento activo',
            self::AwaitingReshipmentPayment => 'Pendiente de nuevo pago de envio',
            self::ManualFollowUp => 'Seguimiento manual',
            self::Completed => 'Finalizado',
        };
    }
}
