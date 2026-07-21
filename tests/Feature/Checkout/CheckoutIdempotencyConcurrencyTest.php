<?php

namespace Tests\Feature\Checkout;

use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckoutIdempotencyConcurrencyTest extends TestCase
{
    public function test_parallel_confirmation_attempts_converge_on_one_database_order(): void
    {
        $workerCount = 8;
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'checkout-idempotency-'.bin2hex(random_bytes(8));
        $database = $prefix.'.sqlite';
        $barrier = $prefix.'.start';
        $key = '7bdf2ba7-a680-4fd7-8662-35b497855b84';
        $review = str_repeat('a', 64);
        $readyFiles = [];
        $processes = [];

        $this->createDatabase($database);

        try {
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $ready = $prefix.'.ready.'.$worker;
                $readyFiles[] = $ready;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Fixtures/checkout-idempotency-worker.php'),
                    $database,
                    $barrier,
                    $ready,
                    $key,
                    $review,
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
            $orderIds = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();

                $this->assertSame(0, $exitCode, trim($process->getErrorOutput()));
                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $orderIds[] = $result['order_id'];
            }

            $this->assertCount(1, array_unique($orderIds));

            $connection = new PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            $this->assertSame($key, (string) $connection->query('SELECT checkout_idempotency_key FROM orders')->fetchColumn());
            $connection = null;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }

            foreach ([...$readyFiles, $barrier, $database, $database.'-wal', $database.'-shm'] as $file) {
                @unlink($file);
            }
        }
    }

    public function test_parallel_distinct_attempts_cannot_claim_the_same_customer_pending_slot(): void
    {
        $workerCount = 8;
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'checkout-pending-slot-'.bin2hex(random_bytes(8));
        $database = $prefix.'.sqlite';
        $barrier = $prefix.'.start';
        $readyFiles = [];
        $processes = [];

        $this->createPendingSlotDatabase($database);

        try {
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $ready = $prefix.'.ready.'.$worker;
                $readyFiles[] = $ready;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Fixtures/checkout-idempotency-worker.php'),
                    $database,
                    $barrier,
                    $ready,
                    sprintf('7bdf2ba7-a680-4fd7-8662-%012d', $worker),
                    hash('sha256', 'revision-'.$worker),
                    '42',
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
            $orderIds = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();

                $this->assertSame(0, $exitCode, trim($process->getErrorOutput()));
                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $orderIds[] = $result['order_id'];
            }

            $this->assertCount(1, array_unique($orderIds));

            $connection = new PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $connection->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            $this->assertSame(42, (int) $connection->query('SELECT pending_payment_owner_id FROM orders')->fetchColumn());
            $connection = null;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }

            foreach ([...$readyFiles, $barrier, $database, $database.'-wal', $database.'-shm'] as $file) {
                @unlink($file);
            }
        }
    }

    private function createDatabase(string $database): void
    {
        $connection = new PDO('sqlite:'.$database);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA busy_timeout = 30000');
        $connection->exec(<<<'SQL'
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                checkout_idempotency_key TEXT NOT NULL UNIQUE,
                checkout_review_reference TEXT NOT NULL UNIQUE
            )
            SQL);
        $connection = null;
    }

    private function createPendingSlotDatabase(string $database): void
    {
        $connection = new PDO('sqlite:'.$database);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA busy_timeout = 30000');
        $connection->exec(<<<'SQL'
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                checkout_idempotency_key TEXT NOT NULL UNIQUE,
                checkout_review_reference TEXT NOT NULL UNIQUE,
                pending_payment_owner_id INTEGER UNIQUE
            )
            SQL);
        $connection = null;
    }
}
