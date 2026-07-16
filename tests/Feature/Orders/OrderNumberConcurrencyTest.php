<?php

namespace Tests\Feature\Orders;

use App\Support\Orders\OrderNumber;
use App\Support\Orders\OrderNumberGenerator;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OrderNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_correlative_increments_independently_for_each_year(): void
    {
        $first = $this->issueNumber(2030);
        $second = $this->issueNumber(2030);
        $nextYear = $this->issueNumber(2031);

        $this->assertSame('PED-2030-000001', $first->code);
        $this->assertSame('PED-2030-000002', $second->code);
        $this->assertSame('PED-2031-000001', $nextYear->code);
        $this->assertDatabaseHas('order_sequences', ['year' => 2030, 'last_number' => 2]);
        $this->assertDatabaseHas('order_sequences', ['year' => 2031, 'last_number' => 1]);
    }

    public function test_a_rolled_back_transaction_does_not_consume_the_correlative(): void
    {
        try {
            DB::transaction(function (): never {
                $number = app(OrderNumberGenerator::class)->next(new DateTimeImmutable('2032-06-01'));

                $this->assertSame(1, $number->number);

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Force rollback.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('order_sequences', ['year' => 2032]);
        $this->assertSame('PED-2032-000001', $this->issueNumber(2032)->code);
    }

    public function test_database_allows_only_one_sequence_row_per_year(): void
    {
        DB::table('order_sequences')->insert([
            'year' => 2033,
            'last_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('order_sequences')->insert([
            'year' => 2033,
            'last_number' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_duplicate_order_numbers_within_the_same_year(): void
    {
        DB::table('orders')->insert($this->orderRow('PED-2034-000001', 2034, 1));

        $this->expectException(QueryException::class);

        DB::table('orders')->insert($this->orderRow('PED-2034-000002', 2034, 1));
    }

    public function test_parallel_processes_issue_unique_gapless_numbers_on_a_shared_database(): void
    {
        $workerCount = 8;
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'order-number-'.bin2hex(random_bytes(8));
        $database = $prefix.'.sqlite';
        $barrier = $prefix.'.start';
        $readyFiles = [];
        $processes = [];

        $this->createTemporarySequenceDatabase($database);

        try {
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $ready = $prefix.'.ready.'.$worker;
                $readyFiles[] = $ready;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Fixtures/order-number-worker.php'),
                    $database,
                    $barrier,
                    $ready,
                    '2035',
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

            $numbers = [];
            $codes = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();

                $this->assertSame(0, $exitCode, trim($process->getErrorOutput()));

                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $numbers[] = $result['number'];
                $codes[] = $result['code'];
            }

            sort($numbers);
            sort($codes);

            $this->assertSame(range(1, $workerCount), $numbers);
            $this->assertSame(array_map(
                static fn (int $number): string => sprintf('PED-2035-%06d', $number),
                range(1, $workerCount),
            ), $codes);
            $this->assertSame($workerCount, count(array_unique($numbers)));

            $connection = new PDO('sqlite:'.$database);
            $lastNumber = (int) $connection->query('SELECT last_number FROM order_sequences WHERE year = 2035')->fetchColumn();
            $connection = null;

            $this->assertSame($workerCount, $lastNumber);
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

    private function issueNumber(int $year): OrderNumber
    {
        return DB::transaction(fn (): OrderNumber => app(OrderNumberGenerator::class)
            ->next(new DateTimeImmutable($year.'-06-01')));
    }

    /** @return array<string, mixed> */
    private function orderRow(string $code, int $year, int $number): array
    {
        return [
            'code' => $code,
            'sequence_year' => $year,
            'sequence_number' => $number,
            'customer_name' => 'Cliente de prueba',
            'customer_email' => 'cliente@example.test',
            'order_status' => 'pending_payment',
            'payment_status' => 'pending',
            'delivery_status' => 'pending',
            'delivery_method' => 'home_delivery',
            'fiscal_document_type' => 'receipt',
            'fiscal_email' => 'fiscal@example.test',
            'products_subtotal_cents' => 11_800,
            'discount_cents' => 0,
            'shipping_fee_cents' => 0,
            'shipping_tax_affectation' => 'taxed',
            'shipping_tax_rate_bps' => 1_800,
            'shipping_net_value_cents' => 0,
            'shipping_tax_cents' => 0,
            'taxable_value_cents' => 10_000,
            'exempt_value_cents' => 0,
            'unaffected_value_cents' => 0,
            'net_value_cents' => 10_000,
            'tax_cents' => 1_800,
            'total_cents' => 11_800,
            'delivery_business_days_min' => 1,
            'delivery_business_days_max' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createTemporarySequenceDatabase(string $database): void
    {
        $connection = new PDO('sqlite:'.$database);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('PRAGMA journal_mode = WAL');
        $connection->exec('PRAGMA busy_timeout = 30000');
        $connection->exec(<<<'SQL'
            CREATE TABLE order_sequences (
                year INTEGER NOT NULL PRIMARY KEY,
                last_number INTEGER NOT NULL CHECK (last_number BETWEEN 1 AND 999999),
                created_at TEXT NULL,
                updated_at TEXT NULL
            )
            SQL);
        $connection = null;
    }
}
