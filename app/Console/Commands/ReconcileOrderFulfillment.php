<?php

namespace App\Console\Commands;

use App\Support\Orders\OrderFulfillmentService;
use Illuminate\Console\Command;

class ReconcileOrderFulfillment extends Command
{
    protected $signature = 'orders:reconcile-fulfillment {--batch=100 : Pedidos a procesar por ejecucion}';

    protected $description = 'Pasa recojos y pagos de reenvio vencidos a seguimiento manual';

    public function handle(OrderFulfillmentService $fulfillment): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $reconciled = $fulfillment->reconcileFollowUps($batch);

        $this->info("Seguimientos reconciliados: {$reconciled}.");

        return self::SUCCESS;
    }
}
