<?php

namespace App\Console\Commands;

use App\Support\Checkout\PendingCheckoutOrderExpirationService;
use Illuminate\Console\Command;

class ExpirePendingCheckoutOrders extends Command
{
    protected $signature = 'orders:expire-pending
        {--batch=100 : Cantidad maxima de pedidos que se revisaran en esta ejecucion}';

    protected $description = 'Vence pedidos pendientes cuya reserva de stock termino';

    public function handle(PendingCheckoutOrderExpirationService $expirations): int
    {
        $batch = filter_var($this->option('batch'), FILTER_VALIDATE_INT);

        if ($batch === false || $batch < 1 || $batch > 1_000) {
            $this->error('La opcion --batch debe ser un entero entre 1 y 1000.');

            return self::INVALID;
        }

        $expired = $expirations->expireDue($batch);

        $this->info("Pedidos vencidos o reconciliados: {$expired}.");

        return self::SUCCESS;
    }
}
