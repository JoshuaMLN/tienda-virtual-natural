<?php

namespace App\Enums;

enum AdminFulfillmentFilter: string
{
    case PickupDueSoon = 'pickup_due_soon';
    case PickupOverdue = 'pickup_overdue';
    case ReshipmentPending = 'reshipment_pending';
    case ManualFollowUp = 'manual_follow_up';

    public function label(): string
    {
        return match ($this) {
            self::PickupDueSoon => 'Recojos por vencer',
            self::PickupOverdue => 'Recojos vencidos',
            self::ReshipmentPending => 'Pago de reenvio pendiente',
            self::ManualFollowUp => 'Seguimiento manual',
        };
    }
}
