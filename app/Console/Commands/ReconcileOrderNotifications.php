<?php

namespace App\Console\Commands;

use App\Support\Orders\Notifications\OrderNotificationDeliveryService;
use Illuminate\Console\Command;

class ReconcileOrderNotifications extends Command
{
    protected $signature = 'orders:reconcile-notifications {--batch=100 : Pedidos a procesar por ejecucion}';

    protected $description = 'Programa recordatorios pendientes de recojo';

    public function handle(OrderNotificationDeliveryService $notifications): int
    {
        $reconciled = $notifications->reconcilePickupReminders((int) $this->option('batch'));

        $this->info("Recordatorios de recojo reconciliados: {$reconciled}.");

        return self::SUCCESS;
    }
}
