<?php

namespace App\Support\Orders;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class OrderNumberGenerator
{
    public function next(?DateTimeInterface $date = null): OrderNumber
    {
        $year = (int) ($date?->format('Y') ?? now()->format('Y'));

        if (DB::transactionLevel() === 0) {
            throw new LogicException('El correlativo debe generarse dentro de la transaccion que crea el pedido.');
        }

        $timestamp = now();
        DB::table('order_sequences')->upsert(
            [[
                'year' => $year,
                'last_number' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]],
            ['year'],
            [
                'last_number' => DB::raw('last_number + 1'),
                'updated_at' => $timestamp,
            ],
        );

        $number = (int) DB::table('order_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->value('last_number');

        if ($number > 999_999) {
            throw new RuntimeException("Se agoto el correlativo anual de pedidos para {$year}.");
        }

        return new OrderNumber($year, $number);
    }
}
