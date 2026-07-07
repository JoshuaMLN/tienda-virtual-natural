<?php

namespace App\Support\Notifications\Providers;

use App\Models\Product;
use App\Support\Notifications\AdminNotification;
use Illuminate\Database\Eloquent\Builder;

class StockAlertNotificationProvider
{
    /**
     * @return AdminNotification[]
     */
    public function getNotifications(): array
    {
        $notifications = [];

        // 1. Productos activos sin stock
        $outOfStockCount = Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', 0)
            ->count();

        if ($outOfStockCount > 0) {
            $message = $outOfStockCount === 1 
                ? '1 producto activo agotado' 
                : "{$outOfStockCount} productos activos agotados";

            $notifications[] = new AdminNotification(
                type: 'danger',
                icon: 'bi-x-octagon',
                title: 'Sin stock',
                message: $message,
                url: route('admin.stock.index', ['estado_stock' => 'sin-stock'])
            );
        }

        // 2. Productos activos con bajo stock
        $lowStockCount = Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->count();

        if ($lowStockCount > 0) {
            $message = $lowStockCount === 1 
                ? '1 producto activo por agotarse' 
                : "{$lowStockCount} productos activos por agotarse";

            $notifications[] = new AdminNotification(
                type: 'warning',
                icon: 'bi-exclamation-triangle',
                title: 'Bajo stock',
                message: $message,
                url: route('admin.stock.index', ['estado_stock' => 'bajo-stock'])
            );
        }

        return $notifications;
    }
}
