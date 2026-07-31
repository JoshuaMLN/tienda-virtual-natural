<?php

declare(strict_types=1);

use App\Enums\AdminOrderAction;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\AdminOrderOperationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

[$script, $database, $barrier, $ready, $orderId, $adminId] = $argv;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config()->set('database.default', 'sqlite');
config()->set('database.connections.sqlite.url', null);
config()->set('database.connections.sqlite.database', $database);
config()->set('database.connections.sqlite.foreign_key_constraints', true);

DB::purge('sqlite');
DB::reconnect('sqlite');
DB::connection('sqlite')->statement('PRAGMA busy_timeout = 30000');

file_put_contents($ready, (string) getmypid());

$deadline = microtime(true) + 20;

while (! is_file($barrier)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Timed out waiting for the concurrency barrier.\n");
        exit(2);
    }

    usleep(10_000);
}

try {
    $order = Order::query()->findOrFail((int) $orderId);
    $admin = User::query()->findOrFail((int) $adminId);
    $result = app(AdminOrderOperationService::class)->perform(
        $order,
        AdminOrderAction::Cancel,
        $admin,
        'Cancelacion concurrente aprobada',
    );

    fwrite(STDOUT, json_encode([
        'order_status' => $result->order_status->value,
        'payment_status' => $result->payment_status->value,
    ], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
}
