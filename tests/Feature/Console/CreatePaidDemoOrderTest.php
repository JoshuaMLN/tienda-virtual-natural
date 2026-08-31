<?php

namespace Tests\Feature\Console;

use App\Enums\DeliveryMethod;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePaidDemoOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearLocalCache();
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_it_creates_a_paid_pickup_order_with_consumed_reservations_and_real_snapshots(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $products = Product::factory()->count(2)->create([
            'stock' => 1,
            'price' => '59.00',
        ]);

        $this->artisan('demo:create-paid-order', [
            '--email' => 'cliente.demo@example.test',
            '--method' => 'pickup',
            '--items' => 2,
        ])->assertSuccessful()
            ->expectsOutputToContain('Pedido pagado')
            ->expectsOutputToContain('Se creo una cuenta cliente local para la prueba.')
            ->expectsOutputToContain('/mi-cuenta/pedidos/PED-')
            ->expectsOutputToContain('/admin/pedidos/PED-');

        $order = Order::query()->with(['items', 'stockReservations', 'statusHistories'])->sole();
        $customer = User::query()->where('email', 'cliente.demo@example.test')->sole();

        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertTrue($order->user->is($customer));
        $this->assertSame(OrderStatus::Confirmed, $order->order_status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(DeliveryMethod::Pickup, $order->delivery_method);
        $this->assertSame('Av. Javier Prado 1234, San Isidro', $order->pickup_address);
        $this->assertCount(2, $order->items);
        $this->assertCount(2, $order->stockReservations);
        $this->assertTrue($order->stockReservations->every(
            fn ($reservation): bool => $reservation->status === ReservationStatus::Consumed,
        ));
        $this->assertSame(11_800, $order->products_subtotal_cents);
        $this->assertSame(11_800, $order->total_cents);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Payment->value,
            'to_status' => PaymentStatus::Paid->value,
            'reason' => 'Pago de demostracion generado por Artisan',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'domain' => OrderHistoryDomain::Order->value,
            'from_status' => OrderStatus::PendingPayment->value,
            'to_status' => OrderStatus::Confirmed->value,
            'reason' => 'Pago de demostracion generado por Artisan',
        ]);

        foreach ($products as $product) {
            $this->assertSame(0, $product->refresh()->stock);
            $this->assertTrue($order->items->contains('product_id', $product->id));
        }
    }

    public function test_it_creates_a_home_delivery_order_with_the_current_district_quote(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '0');
        $customer = User::factory()->create([
            'email' => 'maria@example.test',
            'name' => 'Maria Perez',
            'phone' => '987654321',
        ]);
        Product::factory()->create([
            'stock' => 1,
            'price' => '79.90',
        ]);
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150131',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'San Isidro',
            'shipping_fee' => '11.80',
            'delivery_business_days_min' => 2,
            'delivery_business_days_max' => 3,
        ]);

        $this->artisan('demo:create-paid-order', [
            '--email' => $customer->email,
            '--method' => 'home',
        ])->assertSuccessful();

        $order = Order::query()->sole();

        $this->assertTrue($order->user->is($customer));
        $this->assertSame(1, User::query()->count());
        $this->assertSame(DeliveryMethod::HomeDelivery, $order->delivery_method);
        $this->assertSame('San Isidro', $order->delivery_district);
        $this->assertSame('150131', $order->delivery_ubigeo);
        $this->assertSame(1180, $order->shipping_fee_cents);
        $this->assertSame(9170, $order->total_cents);
        $this->assertSame(2, $order->delivery_business_days_min);
        $this->assertSame(3, $order->delivery_business_days_max);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->delivery_estimated_from);
        $this->assertNotNull($order->delivery_estimated_to);
    }

    public function test_it_rejects_an_administrator_email_without_creating_an_order(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234');
        Product::factory()->create(['stock' => 1]);
        $admin = User::factory()->admin()->create();

        $this->artisan('demo:create-paid-order', [
            '--email' => $admin->email,
            '--method' => 'pickup',
        ])->assertFailed()
            ->expectsOutput('El correo indicado pertenece a una cuenta administrativa.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_fails_without_enough_visible_products_and_rolls_back_the_demo_customer(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234');
        Product::factory()->outOfStock()->create();

        $this->artisan('demo:create-paid-order', [
            '--email' => 'sin-productos@example.test',
            '--method' => 'pickup',
        ])->assertFailed()
            ->expectsOutput('Se necesitan 1 producto visible y con stock para crear el pedido.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_it_rolls_back_customer_and_inventory_when_pickup_is_not_configured(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        $this->artisan('demo:create-paid-order', [
            '--email' => 'recojo-invalido@example.test',
            '--method' => 'pickup',
        ])->assertFailed()
            ->expectsOutput('Configura una direccion de recojo antes de usar la modalidad pickup.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(1, $product->refresh()->stock);
    }

    public function test_it_is_blocked_outside_local_and_testing_environments(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('demo:create-paid-order')
            ->assertFailed()
            ->expectsOutput('Este comando solo puede ejecutarse en entornos local o testing.');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
    }
}
