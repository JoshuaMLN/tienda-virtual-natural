<?php

namespace App\Enums;

enum AdminOrderAction: string
{
    case StartPreparation = 'start_preparation';
    case MarkShipped = 'mark_shipped';
    case MarkReadyForPickup = 'mark_ready_for_pickup';
    case ConfirmPickup = 'confirm_pickup';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::StartPreparation => 'Iniciar preparacion',
            self::MarkShipped => 'Marcar como enviado',
            self::MarkReadyForPickup => 'Marcar listo para recojo',
            self::ConfirmPickup => 'Confirmar recojo',
            self::Cancel => 'Cancelar pedido',
        };
    }

    public function confirmationTitle(): string
    {
        return match ($this) {
            self::StartPreparation => 'Iniciar preparacion del pedido',
            self::MarkShipped => 'Confirmar salida del pedido',
            self::MarkReadyForPickup => 'Confirmar disponibilidad para recojo',
            self::ConfirmPickup => 'Confirmar recojo del cliente',
            self::Cancel => 'Cancelar este pedido',
        };
    }

    public function confirmationMessage(): string
    {
        return match ($this) {
            self::StartPreparation => 'El pedido y su entrega pasaran a preparacion.',
            self::MarkShipped => 'El pedido quedara registrado como enviado y en camino.',
            self::MarkReadyForPickup => 'El pedido quedara disponible para que el cliente lo recoja.',
            self::ConfirmPickup => 'El recojo y el pedido quedaran finalizados.',
            self::Cancel => 'Esta accion cerrara el pedido y no se puede deshacer desde el panel.',
        };
    }

    public function successMessage(): string
    {
        return match ($this) {
            self::StartPreparation => 'La preparacion del pedido fue iniciada.',
            self::MarkShipped => 'El pedido fue marcado como enviado.',
            self::MarkReadyForPickup => 'El pedido fue marcado como listo para recojo.',
            self::ConfirmPickup => 'El recojo fue confirmado y el pedido quedo completado.',
            self::Cancel => 'El pedido fue cancelado correctamente.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::StartPreparation => 'bi-box-seam',
            self::MarkShipped => 'bi-truck',
            self::MarkReadyForPickup => 'bi-shop',
            self::ConfirmPickup => 'bi-check-circle',
            self::Cancel => 'bi-x-circle',
        };
    }

    public function routeName(): string
    {
        return match ($this) {
            self::StartPreparation => 'admin.orders.start-preparation',
            self::MarkShipped => 'admin.orders.mark-shipped',
            self::MarkReadyForPickup => 'admin.orders.mark-ready-pickup',
            self::ConfirmPickup => 'admin.orders.confirm-pickup',
            self::Cancel => 'admin.orders.cancel',
        };
    }

    public function isDestructive(): bool
    {
        return $this === self::Cancel;
    }

    public function requiresReason(): bool
    {
        return $this === self::Cancel;
    }
}
