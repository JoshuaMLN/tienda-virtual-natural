<?php

namespace Tests\Feature\Database;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\E2eSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class E2eSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvironment = app()->environment();
        $this->app['env'] = 'e2e';
        config()->set('e2e.customer.password', Str::random(48));
        config()->set('e2e.admin.password', Str::random(48));
        Storage::fake('local');
        Setting::clearLocalCache();
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnvironment;
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_it_seeds_deterministic_order_scenarios_through_the_real_order_domain(): void
    {
        app(E2eSeeder::class)->run();

        $customer = User::query()
            ->where('email', config('e2e.customer.email'))
            ->sole();
        $standardHome = Order::query()
            ->with('stockReservations')
            ->where('delivery_method', DeliveryMethod::HomeDelivery->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('shipping_fee_cents', 1200)
            ->sole();
        $freeShippingHome = Order::query()
            ->with('stockReservations')
            ->where('delivery_method', DeliveryMethod::HomeDelivery->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('products_subtotal_cents', 14_970)
            ->where('shipping_fee_cents', 0)
            ->sole();
        $pickup = Order::query()
            ->with('stockReservations')
            ->where('customer_name', $customer->name)
            ->where('delivery_method', DeliveryMethod::Pickup->value)
            ->where('payment_status', PaymentStatus::Paid->value)
            ->sole();
        $pending = Order::query()
            ->with('stockReservations')
            ->where('order_status', OrderStatus::PendingPayment->value)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->sole();
        $fiscal = Order::query()
            ->where('customer_name', E2eSeeder::FISCAL_FIXTURE_CUSTOMER_NAME)
            ->sole();

        $this->assertCount(5, Order::all());
        $this->assertTrue($standardHome->user->is($customer));
        $this->assertSame(5190, $standardHome->total_cents);
        $this->assertNotNull($standardHome->delivery_estimated_from);
        $this->assertNotNull($standardHome->delivery_estimated_to);
        $this->assertTrue($standardHome->stockReservations->every(
            fn ($reservation): bool => $reservation->status === ReservationStatus::Consumed,
        ));

        $this->assertTrue($freeShippingHome->user->is($customer));
        $this->assertSame(14_970, $freeShippingHome->total_cents);
        $this->assertTrue($freeShippingHome->stockReservations->every(
            fn ($reservation): bool => $reservation->status === ReservationStatus::Consumed,
        ));

        $this->assertTrue($pickup->user->is($customer));
        $this->assertSame(0, $pickup->shipping_fee_cents);
        $this->assertNotNull($pickup->delivery_estimated_from);
        $this->assertNotNull($pickup->delivery_estimated_to);
        $this->assertTrue($pickup->stockReservations->every(
            fn ($reservation): bool => $reservation->status === ReservationStatus::Consumed,
        ));

        $this->assertTrue($pending->user->is($customer));
        $this->assertSame($customer->id, $pending->pending_payment_owner_id);
        $this->assertNotNull($pending->reservation_expires_at);
        $this->assertTrue($pending->reservation_expires_at->isAfter(now()->addMinutes(110)));
        $this->assertTrue($pending->stockReservations->every(
            fn ($reservation): bool => $reservation->status === ReservationStatus::Active,
        ));

        $this->assertSame(16, Product::query()->where('sku', 'E2E-OMEGA-3')->sole()->stock);
        $this->assertSame(8, Product::query()->where('sku', 'E2E-MAGNESIO')->sole()->stock);
        $this->assertSame(7980, $fiscal->total_cents);
        $this->assertDatabaseHas('fiscal_documents', [
            'order_id' => $fiscal->id,
            'series' => 'B001',
            'correlative' => '90000002',
        ]);
        $this->assertDatabaseCount('fiscal_document_file_versions', 1);
        $this->assertDatabaseCount('fiscal_document_corrections', 1);
        Storage::disk('local')->assertExists('fiscal-documents/e2e/boleta-vigente.pdf');
    }
}
