<?php

namespace App\Enums;

enum OrderNotificationType: string
{
    case Created = 'created';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Shipped = 'shipped';
    case PickupReady = 'pickup_ready';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case PickupMidpointReminder = 'pickup_midpoint_reminder';
    case Pickup48HoursReminder = 'pickup_48_hours_reminder';
    case PickupDeadlineReminder = 'pickup_deadline_reminder';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Pedido creado',
            self::Cancelled => 'Pedido cancelado',
            self::Expired => 'Pedido vencido',
            self::Shipped => 'Pedido en camino',
            self::PickupReady => 'Pedido listo para recoger',
            self::Delivered => 'Pedido entregado',
            self::PickedUp => 'Pedido recogido',
            self::PickupMidpointReminder => 'Recordatorio de recojo a mitad de plazo',
            self::Pickup48HoursReminder => 'Recordatorio de recojo a 48 horas',
            self::PickupDeadlineReminder => 'Recordatorio de recojo vencido',
        };
    }

    public function isPickupReminder(): bool
    {
        return in_array($this, [
            self::PickupReady,
            self::PickupMidpointReminder,
            self::Pickup48HoursReminder,
            self::PickupDeadlineReminder,
        ], true);
    }

    /** @return list<self> */
    public static function pickupReminderTypes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->isPickupReminder(),
        ));
    }
}
