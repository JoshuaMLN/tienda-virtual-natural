<?php

declare(strict_types=1);

[$script, $database, $barrier, $ready, $idempotencyKey, $reviewReference] = $argv;
$pendingOwnerId = $argv[6] ?? null;

touch($ready);

$deadline = microtime(true) + 20;

while (! is_file($barrier)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, 'Concurrency barrier timeout.');
        exit(2);
    }

    usleep(10_000);
}

$connection = new PDO('sqlite:'.$database);
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->exec('PRAGMA busy_timeout = 30000');
$connection->beginTransaction();

try {
    $parameters = [
        'key' => $idempotencyKey,
        'review' => $reviewReference,
    ];
    $insert = $pendingOwnerId === null
        ? $connection->prepare('INSERT OR IGNORE INTO orders (checkout_idempotency_key, checkout_review_reference) VALUES (:key, :review)')
        : $connection->prepare('INSERT OR IGNORE INTO orders (checkout_idempotency_key, checkout_review_reference, pending_payment_owner_id) VALUES (:key, :review, :owner)');

    if ($pendingOwnerId !== null) {
        $parameters['owner'] = (int) $pendingOwnerId;
    }

    $insert->execute($parameters);
    $select = $pendingOwnerId === null
        ? $connection->prepare('SELECT id FROM orders WHERE checkout_idempotency_key = :value')
        : $connection->prepare('SELECT id FROM orders WHERE pending_payment_owner_id = :value');
    $select->execute(['value' => $pendingOwnerId === null ? $idempotencyKey : (int) $pendingOwnerId]);
    $orderId = $select->fetchColumn();

    $connection->commit();
    echo json_encode(['order_id' => (int) $orderId], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
