<?php

namespace App\Enums;

enum CustomerOrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentFailed = 'payment_failed';
    case Preparing = 'preparing';
    case InTransit = 'in_transit';
    case ReadyForPickup = 'ready_for_pickup';
    case Delivered = 'delivered';
    case PickedUp = 'picked_up';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::PaymentFailed => 'Pago no completado',
            self::Preparing => 'En preparacion',
            self::InTransit => 'En camino',
            self::ReadyForPickup => 'Listo para recoger',
            self::Delivered => 'Entregado',
            self::PickedUp => 'Recogido',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Vencido',
            self::Refunded => 'Reembolsado',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment, self::PaymentFailed => 'warning',
            self::Preparing => 'info',
            self::InTransit, self::ReadyForPickup => 'progress',
            self::Delivered, self::PickedUp => 'success',
            self::Cancelled, self::Expired => 'danger',
            self::Refunded => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingPayment => 'bi-clock',
            self::PaymentFailed => 'bi-exclamation-circle',
            self::Preparing => 'bi-box-seam',
            self::InTransit => 'bi-truck',
            self::ReadyForPickup => 'bi-shop',
            self::Delivered, self::PickedUp => 'bi-check-circle',
            self::Cancelled => 'bi-x-circle',
            self::Expired => 'bi-hourglass-bottom',
            self::Refunded => 'bi-arrow-counterclockwise',
        };
    }
}
