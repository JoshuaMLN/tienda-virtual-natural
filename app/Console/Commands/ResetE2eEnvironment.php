<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DeliveryDistrict;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\E2eSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class ResetE2eEnvironment extends Command
{
    private const DATABASE = 'tienda_virtual_natural_e2e';

    protected $signature = 'e2e:reset
        {--check : Ejecuta las guardas E2E sin bloquear ni restablecer la base}';

    protected $description = 'Recrea exclusivamente la base de datos local de Playwright E2E';

    public function handle(): int
    {
        $lockAcquired = false;

        try {
            $this->assertSafeEnvironment();

            if ($this->option('check')) {
                $this->info('Preflight E2E completado correctamente.');

                return self::SUCCESS;
            }

            $lockAcquired = $this->acquireResetLock();

            if (! $lockAcquired) {
                throw new RuntimeException('Ya hay otro reset E2E en ejecucion.');
            }

            $exitCode = $this->call('migrate:fresh', [
                '--database' => 'mysql',
                '--force' => true,
                '--seed' => true,
                '--seeder' => E2eSeeder::class,
            ]);

            if ($exitCode !== self::SUCCESS) {
                throw new RuntimeException('migrate:fresh o E2eSeeder devolvio un codigo de error.');
            }

            $this->assertSentinelData();
            $this->info('Base E2E restablecida y sembrada correctamente.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Reset E2E rechazado o fallido: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($lockAcquired) {
                $this->releaseResetLock();
            }
        }
    }

    private function assertSafeEnvironment(): void
    {
        if (app()->environment() !== 'e2e') {
            throw new RuntimeException('APP_ENV debe ser exactamente e2e.');
        }

        if (app()->configurationIsCached()) {
            throw new RuntimeException('La configuracion cacheada no esta permitida para el reset E2E.');
        }

        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('La conexion por defecto debe ser exactamente mysql.');
        }

        $connection = config('database.connections.mysql');

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('La conexion mysql debe usar el driver mysql.');
        }

        $dbUrl = getenv('DB_URL');
        $dbUrl = $dbUrl === false ? ($_ENV['DB_URL'] ?? $_SERVER['DB_URL'] ?? null) : $dbUrl;

        if (filled($connection['url'] ?? null) || filled($dbUrl)) {
            throw new RuntimeException('DB_URL no esta permitido para el reset E2E.');
        }

        if (($connection['database'] ?? null) !== self::DATABASE || config('e2e.database') !== self::DATABASE) {
            throw new RuntimeException('La base configurada no coincide exactamente con la base E2E aprobada.');
        }

        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('El driver PDO real debe ser MySQL.');
        }

        $database = DB::connection()->selectOne('SELECT DATABASE() AS database_name')->database_name ?? null;

        if ($database !== self::DATABASE || ! str_ends_with($database, '_e2e')) {
            throw new RuntimeException('SELECT DATABASE() debe devolver exactamente la base E2E aprobada.');
        }
    }

    private function acquireResetLock(): bool
    {
        $result = DB::connection()->selectOne(
            'SELECT GET_LOCK(?, 0) AS reset_lock',
            [config('e2e.reset_lock_name')],
        );

        return (int) ($result->reset_lock ?? 0) === 1;
    }

    private function releaseResetLock(): void
    {
        DB::connection()->selectOne(
            'SELECT RELEASE_LOCK(?) AS reset_lock',
            [config('e2e.reset_lock_name')],
        );
    }

    private function assertSentinelData(): void
    {
        $customerReady = User::query()
            ->where('email', config('e2e.customer.email'))
            ->where('role', UserRole::Customer->value)
            ->whereNotNull('email_verified_at')
            ->whereNotNull('terms_accepted_at')
            ->exists();

        $adminReady = User::query()
            ->where('email', config('e2e.admin.email'))
            ->where('role', UserRole::Admin->value)
            ->whereNotNull('email_verified_at')
            ->exists();

        $catalogReady = Category::query()->where('slug', 'e2e-suplementos')->exists()
            && Product::query()->whereIn('sku', ['E2E-OMEGA-3', 'E2E-MAGNESIO'])->count() === 2;

        $deliveryReady = DeliveryDistrict::query()->where('ubigeo', '150131')->where('is_active', true)->exists();

        if (! $customerReady || ! $adminReady || ! $catalogReady || ! $deliveryReady) {
            throw new RuntimeException('La comprobacion de datos centinela E2E fallo.');
        }
    }
}
