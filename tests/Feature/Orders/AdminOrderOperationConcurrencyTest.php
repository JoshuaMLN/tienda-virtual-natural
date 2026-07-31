<?php

namespace Tests\Feature\Orders;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminOrderOperationConcurrencyTest extends TestCase
{
    public function test_parallel_paid_cancellations_converge_without_duplicate_restock_or_history(): void
    {
        $workerCount = 4;
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'admin-order-operation-'.bin2hex(random_bytes(8));
        $database = $prefix.'.sqlite';
        $barrier = $prefix.'.start';
        $readyFiles = [];
        $processes = [];
        $originalDefault = config('database.default');
        $originalDatabase = config('database.connections.sqlite.database');

        touch($database);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.url', null);
        config()->set('database.connections.sqlite.database', $database);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->paid()->create();
        $product = Product::factory()->create(['stock' => 7]);
        $item = OrderItem::factory()->for($order)->for($product)->create(['quantity' => 3]);
        StockReservation::factory()->forOrderItem($item)->create([
            'quantity' => 3,
            'status' => ReservationStatus::Consumed,
            'consumed_at' => now(),
        ]);

        try {
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $ready = $prefix.'.ready.'.$worker;
                $readyFiles[] = $ready;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Fixtures/admin-order-operation-worker.php'),
                    $database,
                    $barrier,
                    $ready,
                    (string) $order->id,
                    (string) $admin->id,
                ]);
                $process->setTimeout(45);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 15;

            do {
                $readyCount = count(array_filter($readyFiles, static fn (string $file): bool => is_file($file)));

                if ($readyCount === $workerCount) {
                    break;
                }

                usleep(20_000);
            } while (microtime(true) < $deadline);

            $this->assertSame($workerCount, $readyCount, 'Not all workers reached the concurrency barrier.');
            touch($barrier);

            foreach ($processes as $process) {
                $exitCode = $process->wait();

                $this->assertSame(0, $exitCode, trim($process->getErrorOutput()));
                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('cancelled', $result['order_status']);
                $this->assertSame(PaymentStatus::RefundPending->value, $result['payment_status']);
            }

            DB::disconnect('sqlite');
            $connection = new PDO('sqlite:'.$database);
            $this->assertSame(10, (int) $connection->query(
                "SELECT stock FROM products WHERE id = {$product->id}",
            )->fetchColumn());
            $this->assertSame(1, (int) $connection->query(
                'SELECT COUNT(*) FROM stock_reservations WHERE restocked_at IS NOT NULL',
            )->fetchColumn());
            $this->assertSame(1, (int) $connection->query(
                "SELECT COUNT(*) FROM inventory_movements WHERE reason = 'Reposicion por cancelacion pagada'",
            )->fetchColumn());
            $this->assertSame(1, (int) $connection->query(
                "SELECT COUNT(*) FROM order_status_histories WHERE domain = 'payment' AND to_status = 'refund_pending'",
            )->fetchColumn());
            $connection = null;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }

            DB::purge('sqlite');
            config()->set('database.default', $originalDefault);
            config()->set('database.connections.sqlite.database', $originalDatabase);

            foreach ([...$readyFiles, $barrier, $database, $database.'-wal', $database.'-shm'] as $file) {
                @unlink($file);
            }
        }
    }
}
