<?php

namespace App\Support\Notifications\Providers;

use App\Enums\AdminFulfillmentFilter;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Models\Order;
use App\Support\Notifications\AdminNotification;

class FulfillmentAlertNotificationProvider
{
    /**
     * @return AdminNotification[]
     */
    public function getNotifications(): array
    {
        $now = now();
        $pickupQuery = Order::query()
            ->where('delivery_method', DeliveryMethod::Pickup)
            ->where('delivery_status', DeliveryStatus::ReadyForPickup)
            ->whereNotNull('pickup_deadline_at');

        $overduePickupCount = (clone $pickupQuery)
            ->where('pickup_deadline_at', '<=', $now)
            ->count();

        $pickupDueSoonCount = (clone $pickupQuery)
            ->where('pickup_deadline_at', '>', $now)
            ->where('pickup_deadline_at', '<=', $now->copy()->addHours(48))
            ->count();

        $reshipmentPendingCount = Order::query()
            ->where('delivery_tracking_status', DeliveryTrackingStatus::AwaitingReshipmentPayment)
            ->count();

        $manualFollowUpCount = Order::query()
            ->where('delivery_tracking_status', DeliveryTrackingStatus::ManualFollowUp)
            ->where('delivery_method', DeliveryMethod::HomeDelivery)
            ->count();

        return array_values(array_filter([
            $this->notification(
                count: $overduePickupCount,
                type: 'danger',
                icon: 'bi-clock-history',
                title: 'Recojos vencidos',
                singularMessage: '1 pedido supero su plazo de recojo',
                pluralMessage: "{$overduePickupCount} pedidos superaron su plazo de recojo",
                filter: AdminFulfillmentFilter::PickupOverdue,
            ),
            $this->notification(
                count: $pickupDueSoonCount,
                type: 'warning',
                icon: 'bi-alarm',
                title: 'Recojos por vencer',
                singularMessage: '1 pedido vence dentro de las proximas 48 horas',
                pluralMessage: "{$pickupDueSoonCount} pedidos vencen dentro de las proximas 48 horas",
                filter: AdminFulfillmentFilter::PickupDueSoon,
            ),
            $this->notification(
                count: $reshipmentPendingCount,
                type: 'warning',
                icon: 'bi-arrow-repeat',
                title: 'Reenvios pendientes',
                singularMessage: '1 pedido espera el pago de un nuevo envio',
                pluralMessage: "{$reshipmentPendingCount} pedidos esperan el pago de un nuevo envio",
                filter: AdminFulfillmentFilter::ReshipmentPending,
            ),
            $this->notification(
                count: $manualFollowUpCount,
                type: 'info',
                icon: 'bi-person-check',
                title: 'Seguimiento manual',
                singularMessage: '1 pedido requiere seguimiento manual',
                pluralMessage: "{$manualFollowUpCount} pedidos requieren seguimiento manual",
                filter: AdminFulfillmentFilter::ManualFollowUp,
            ),
        ]));
    }

    private function notification(
        int $count,
        string $type,
        string $icon,
        string $title,
        string $singularMessage,
        string $pluralMessage,
        AdminFulfillmentFilter $filter,
    ): ?AdminNotification {
        if ($count === 0) {
            return null;
        }

        return new AdminNotification(
            type: $type,
            icon: $icon,
            title: $title,
            message: $count === 1 ? $singularMessage : $pluralMessage,
            url: route('admin.orders.index', ['seguimiento' => $filter->value]),
        );
    }
}
