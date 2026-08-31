<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeliveryAttemptConcurrencyTest extends TestCase
{
    public function test_parallel_retries_with_the_same_token_create_one_delivery_attempt(): void
    {
        $workerCount = 4;
        $prefix = sys_get_temp_dir().DIRECTORY_SEPARATOR.'delivery-attempt-'.bin2hex(random_bytes(8));
        $database = $prefix.'.sqlite';
        $barrier = $prefix.'.start';
        $readyFiles = [];
        $processes = [];
        $token = (string) Str::uuid();
        $originalDefault = config('database.default');
        $originalDatabase = config('database.connections.sqlite.database');

        touch($database);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.url', null);
        config()->set('database.connections.sqlite.database', $database);
        DB::purge('sqlite');
        Artisan::call('migrate:fresh', ['--force' => true]);

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->shipped()->create();

        try {
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $ready = $prefix.'.ready.'.$worker;
                $readyFiles[] = $ready;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Fixtures/delivery-attempt-worker.php'),
                    $database,
                    $barrier,
                    $ready,
                    (string) $order->id,
                    (string) $admin->id,
                    $token,
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
            $attemptIds = [];

            foreach ($processes as $process) {
                $exitCode = $process->wait();

                $this->assertSame(0, $exitCode, trim($process->getErrorOutput()));
                $result = json_decode(trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);
                $attemptIds[] = $result['attempt_id'];
                $this->assertSame($token, $result['operation_token']);
            }

            $this->assertCount(1, array_unique($attemptIds));

            DB::disconnect('sqlite');
            $connection = new PDO('sqlite:'.$database);
            $this->assertSame(1, (int) $connection->query(
                'SELECT COUNT(*) FROM delivery_attempts',
            )->fetchColumn());
            $this->assertSame(1, (int) $connection->query(
                'SELECT COUNT(*) FROM delivery_attempts WHERE consumes_attempt = 1',
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
