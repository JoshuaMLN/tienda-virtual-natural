<?php

namespace Tests\Feature\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Enums\OrderHistoryDomain;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Checkout\CheckoutOrderCreationService;
use App\Support\Inventory\InsufficientStockException;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        Setting::setValue(Setting::STOCK_RESERVATION_MINUTES, '20');
        CarbonImmutable::setTestNow('2026-07-21 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_confirmation_creates_the_order_snapshots_reservations_and_preserves_concurrent_cart_additions(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Omega 3 Premium',
            'sku' => 'OMEGA-120',
            'short_description' => '120 capsulas',
            'price' => '59.00',
            'stock' => 5,
        ]);
        $product->images()->create([
            'url' => 'https://images.example.test/omega.webp',
            'is_primary' => true,
        ]);
        $this->reviewPickup($user, [$product->id => 2]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $terms = $this->activeTerms();
        $termsBody = $terms->body;
        $termsFingerprint = session('checkout.draft.review.terms_content_fingerprint');

        app(CartService::class)->add($product, 2);

        $response = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $reviewReference,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::PendingPayment->value)
            ->assertJsonPath('idempotent_replay', false);

        $order = Order::query()->with(['items', 'stockReservations', 'statusHistories'])->sole();

        $this->assertSame($order->code, $response->json('order.code'));
        $this->assertSame(route('checkout.order.pending', $order->code), $response->json('redirect_url'));
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame($user->id, $order->pending_payment_owner_id);
        $this->assertSame($reviewReference, $order->checkout_review_reference);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame('https://images.example.test/omega.webp', $order->items->sole()->product_image);
        $this->assertSame(5900, $order->items->sole()->unit_price_cents);
        $this->assertSame(11_800, $order->total_cents);
        $this->assertSame(ReservationStatus::Active, $order->stockReservations->sole()->status);
        $this->assertSame('2026-07-21 10:20:00', $order->reservation_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame($order->reservation_expires_at->format('Y-m-d H:i:s'), $order->stockReservations->sole()->expires_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($order->cart_cleaned_at);
        $this->assertSame($terms->id, $order->terms_document_id);
        $this->assertTrue($order->termsDocument->is($terms));
        $this->assertSame($terms->version, $order->terms_document_version);
        $this->assertSame('2026-07-21 10:00:00', $order->terms_accepted_at->format('Y-m-d H:i:s'));
        $this->assertSame($termsFingerprint, $order->terms_content_fingerprint);
        $this->assertSame($termsBody, $order->terms_snapshot['body']);
        $this->assertSame($terms->title, $order->terms_snapshot['title']);
        $this->assertSame(4, $order->statusHistories->count());
        $this->assertSame(1, $order->statusHistories->where('domain', OrderHistoryDomain::Reservation)->count());
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertNull(session('checkout.draft'));

        DB::table('legal_documents')->where('id', $terms->id)->delete();

        $order->refresh();
        $this->assertNull($order->terms_document_id);
        $this->assertSame($termsBody, $order->terms_snapshot['body']);

        $this->get($response->json('redirect_url'))
            ->assertOk()
            ->assertSee($order->code)
            ->assertSee('S/ 118.00');
    }

    public function test_same_idempotency_key_returns_the_same_order_without_reserving_or_cleaning_twice(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '25.00', 'stock' => 8]);
        $this->reviewPickup($user, [$product->id => 2]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        app(CartService::class)->add($product, 1);
        $key = (string) Str::uuid();
        $payload = [
            'review_reference' => $reviewReference,
            'idempotency_key' => $key,
        ];

        $first = $this->postJson(route('checkout.confirm'), $payload)
            ->assertCreated()
            ->assertJsonPath('idempotent_replay', false);
        $second = $this->postJson(route('checkout.confirm'), $payload)
            ->assertOk()
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame($first->json('order.code'), $second->json('order.code'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(6, $product->refresh()->stock);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function test_a_delayed_idempotent_retry_does_not_clear_a_new_checkout_draft(): void
    {
        $user = User::factory()->create();
        $firstProduct = Product::factory()->create(['price' => '25.00', 'stock' => 5]);
        $nextProduct = Product::factory()->create(['price' => '15.00', 'stock' => 8]);
        $this->reviewPickup($user, [$firstProduct->id => 1]);
        $firstReviewReference = session('checkout.draft.review.fingerprint');
        $firstKey = (string) Str::uuid();

        $firstResponse = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $firstReviewReference,
            'idempotency_key' => $firstKey,
        ])->assertCreated();
        $order = Order::query()->where('code', $firstResponse->json('order.code'))->firstOrFail();

        $this->delete(route('checkout.order.cancel', $order->code))->assertRedirect(route('shop.cart'));
        $this->reviewPickup($user, [$nextProduct->id => 2]);
        $nextReviewReference = session('checkout.draft.review.fingerprint');

        $this->postJson(route('checkout.confirm'), [
            'review_reference' => $firstReviewReference,
            'idempotency_key' => $firstKey,
        ])->assertOk()
            ->assertJsonPath('order.code', $order->code)
            ->assertJsonPath('idempotent_replay', true);

        $this->assertSame($nextReviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $nextProduct->id,
            'quantity' => 2,
        ]);
    }

    public function test_existing_pending_order_redirects_checkout_and_blocks_a_new_confirmation_without_touching_the_cart(): void
    {
        $user = User::factory()->create();
        $reservedProduct = Product::factory()->create(['price' => '25.00', 'stock' => 5]);
        $newProduct = Product::factory()->create(['price' => '15.00', 'stock' => 8]);
        $this->reviewPickup($user, [$reservedProduct->id => 1]);
        $firstOrder = app(CheckoutOrderCreationService::class)->confirm(
            $user,
            session('checkout.draft.review.fingerprint'),
            (string) Str::uuid(),
        )->order;

        $this->reviewPickup($user, [$newProduct->id => 2]);
        $newReviewReference = session('checkout.draft.review.fingerprint');

        $this->get(route('checkout.index'))
            ->assertRedirect(route('checkout.order.pending', $firstOrder->code));

        $response = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $newReviewReference,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('pending_order', true)
            ->assertJsonPath('order.code', $firstOrder->code)
            ->assertJsonPath('redirect_url', route('checkout.order.pending', $firstOrder->code));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_reservations', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(4, $reservedProduct->refresh()->stock);
        $this->assertSame(8, $newProduct->refresh()->stock);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $newProduct->id,
            'quantity' => 2,
        ]);
        $this->assertSame($newReviewReference, session('checkout.draft.review.fingerprint'));
    }

    public function test_confirmation_expires_a_stale_order_before_creating_a_new_one(): void
    {
        $user = User::factory()->create();
        $expiredProduct = Product::factory()->create(['price' => '25.00', 'stock' => 5]);
        $newProduct = Product::factory()->create(['price' => '15.00', 'stock' => 8]);
        $this->reviewPickup($user, [$expiredProduct->id => 2]);
        $expiredOrder = app(CheckoutOrderCreationService::class)->confirm(
            $user,
            session('checkout.draft.review.fingerprint'),
            (string) Str::uuid(),
        )->order;

        $this->reviewPickup($user, [$newProduct->id => 1]);
        $newReviewReference = session('checkout.draft.review.fingerprint');
        CarbonImmutable::setTestNow($expiredOrder->reservation_expires_at);

        $response = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $newReviewReference,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $newOrder = Order::query()->where('code', $response->json('order.code'))->firstOrFail();

        $this->assertSame(OrderStatus::Expired, $expiredOrder->refresh()->order_status);
        $this->assertSame(PaymentStatus::Expired, $expiredOrder->payment_status);
        $this->assertNull($expiredOrder->pending_payment_owner_id);
        $this->assertSame(5, $expiredProduct->refresh()->stock);
        $this->assertSame(OrderStatus::PendingPayment, $newOrder->order_status);
        $this->assertSame($user->id, $newOrder->pending_payment_owner_id);
        $this->assertSame(7, $newProduct->refresh()->stock);
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(1, Order::query()->whereNotNull('pending_payment_owner_id')->count());
    }

    public function test_home_delivery_and_invoice_are_frozen_from_the_validated_checkout_data(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->create([
            'recipient_name' => 'Maria Receptora',
            'phone' => '987654321',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'ubigeo' => '150140',
            'address_line' => 'Av. Primavera 123',
            'reference' => 'Puerta verde',
        ]);
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150140',
            'province_code' => '1501',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'shipping_fee' => '11.80',
            'is_active' => true,
        ]);
        $product = Product::factory()->create(['price' => '118.00', 'stock' => 5]);
        $this->actingAs($user);
        app(CartService::class)->add($product, 1);
        $quote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])->assertOk()->json('delivery.quote_reference');

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto Factura',
            'contact_phone' => '999888777',
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'quote_reference' => $quote,
            'address_choice' => 'address:'.$address->id,
        ])->assertSessionHasNoErrors();
        $this->post(route('checkout.review'), [
            'fiscal_document_type' => 'invoice',
            'invoice_ruc' => '20131312955',
            'invoice_business_name' => 'Empresa Natural SAC',
            'invoice_address' => 'Av. Empresa 456, San Isidro',
            'invoice_email' => 'facturas@example.test',
            'terms_document_id' => $this->activeTerms()->id,
            'terms_accepted' => '1',
        ])->assertSessionHasNoErrors();

        app(CheckoutOrderCreationService::class)->confirm(
            $user,
            session('checkout.draft.review.fingerprint'),
            (string) Str::uuid(),
        );
        $order = Order::query()->sole();

        $this->assertTrue($order->customerAddress->is($address));
        $this->assertSame('Contacto Factura', $order->customer_name);
        $this->assertSame('Maria Receptora', $order->delivery_recipient_name);
        $this->assertSame('150140', $order->delivery_ubigeo);
        $this->assertSame('Av. Primavera 123', $order->delivery_address);
        $this->assertSame('Puerta verde', $order->delivery_reference);
        $this->assertNull($order->pickup_address);
        $this->assertSame('invoice', $order->fiscal_document_type->value);
        $this->assertSame('20131312955', $order->fiscal_identity_document_number);
        $this->assertSame('Empresa Natural SAC', $order->fiscal_business_name);
        $this->assertSame('Av. Empresa 456, San Isidro', $order->fiscal_address);
        $this->assertSame(1180, $order->shipping_fee_cents);
        $this->assertSame(180, $order->shipping_tax_cents);
        $this->assertSame(12_980, $order->total_cents);
    }

    public function test_commercial_change_requires_acceptance_and_the_accepted_snapshot_is_the_one_created(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, [$product->id => 1]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $key = (string) Str::uuid();
        $product->update(['price' => '79.00']);

        $change = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $reviewReference,
            'idempotency_key' => $key,
        ])->assertStatus(409)
            ->assertJsonPath('revalidation.status', 'changed')
            ->assertJsonPath('revalidation.current.amounts.total_cents', 7900);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $product->refresh()->stock);

        $created = $this->postJson(route('checkout.confirm'), [
            'review_reference' => $reviewReference,
            'idempotency_key' => $key,
            'accepted_proposal_reference' => $change->json('revalidation.proposal_reference'),
        ])->assertCreated();

        $order = Order::query()->with('items')->sole();

        $this->assertSame($created->json('order.code'), $order->code);
        $this->assertSame(7900, $order->items->sole()->unit_price_cents);
        $this->assertSame(7900, $order->total_cents);
        $this->assertSame(4, $product->refresh()->stock);
    }

    public function test_failure_during_the_second_reservation_rolls_back_every_domain_side_effect(): void
    {
        $user = User::factory()->create();
        $firstProduct = Product::factory()->create(['stock' => 5]);
        $secondProduct = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, [
            $firstProduct->id => 1,
            $secondProduct->id => 1,
        ]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $key = (string) Str::uuid();

        DB::statement(sprintf(
            'CREATE TRIGGER checkout_force_stock_failure AFTER INSERT ON orders BEGIN UPDATE products SET stock = 0 WHERE id = %d; END',
            $secondProduct->id,
        ));

        try {
            app(CheckoutOrderCreationService::class)->confirm(
                $user,
                $reviewReference,
                $key,
            );
            $this->fail('Se esperaba que la segunda reserva fallara por stock insuficiente.');
        } catch (InsufficientStockException) {
            $this->assertTrue(true);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS checkout_force_stock_failure');
        }

        $this->assertDatabaseCount('order_sequences', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(5, $firstProduct->refresh()->stock);
        $this->assertSame(5, $secondProduct->refresh()->stock);
        $this->assertDatabaseHas('cart_items', ['product_id' => $firstProduct->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $secondProduct->id, 'quantity' => 1]);
        $this->assertNotNull(session('checkout.draft.review'));
    }

    public function test_database_guarantees_unique_attempt_and_review_references(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, [$product->id => 1]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $key = (string) Str::uuid();

        app(CheckoutOrderCreationService::class)->confirm($user, $reviewReference, $key);
        $order = Order::query()->sole();
        $duplicate = $order->getAttributes();
        unset($duplicate['id']);
        $duplicate['code'] = 'PED-2098-000001';
        $duplicate['sequence_year'] = 2098;
        $duplicate['sequence_number'] = 1;
        $duplicate['checkout_review_reference'] = str_repeat('b', 64);

        try {
            DB::table('orders')->insert($duplicate);
            $this->fail('La base de datos debio rechazar la clave idempotente repetida.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $duplicate['code'] = 'PED-2099-000001';
        $duplicate['sequence_year'] = 2099;
        $duplicate['checkout_idempotency_key'] = (string) Str::uuid();
        $duplicate['checkout_review_reference'] = $reviewReference;

        try {
            DB::table('orders')->insert($duplicate);
            $this->fail('La base de datos debio rechazar la revision repetida.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_confirmation_route_is_protected_and_rejects_invalid_attempt_keys(): void
    {
        $payload = [
            'review_reference' => str_repeat('a', 64),
            'idempotency_key' => 'manipulada',
        ];

        $this->postJson(route('checkout.confirm'), $payload)
            ->assertRedirect(route('login'));

        $validPayload = [
            'review_reference' => str_repeat('a', 64),
            'idempotency_key' => (string) Str::uuid(),
        ];
        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('checkout.confirm'), $validPayload)
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('checkout.confirm'), $validPayload)
            ->assertForbidden();

        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson(route('checkout.confirm'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');

        $middleware = Route::getRoutes()->getByName('checkout.confirm')?->gatherMiddleware() ?? [];
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('customer', $middleware);
        $this->assertContains('verified', $middleware);
    }

    public function test_pending_order_page_is_private_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($owner, [$product->id => 1]);
        $order = app(CheckoutOrderCreationService::class)->confirm(
            $owner,
            session('checkout.draft.review.fingerprint'),
            (string) Str::uuid(),
        )->order;

        $this->actingAs($other)
            ->get(route('checkout.order.pending', $order->code))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('checkout.order.pending', $order->code))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($order->code);
    }

    /** @param array<int, int> $quantitiesByProduct */
    private function reviewPickup(User $user, array $quantitiesByProduct): void
    {
        $this->actingAs($user);

        foreach ($quantitiesByProduct as $productId => $quantity) {
            app(CartService::class)->add($productId, $quantity);
        }

        $quote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])->assertOk()->json('delivery.quote_reference');

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto de compra',
            'contact_phone' => '987654321',
            'delivery_method' => DeliveryMethod::Pickup->value,
            'quote_reference' => $quote,
        ])->assertSessionHasNoErrors();

        $this->post(route('checkout.review'), [
            'fiscal_document_type' => 'receipt',
            'receipt_identity_document_type' => 'dni',
            'receipt_identity_document_number' => '12345678',
            'receipt_first_names' => 'Maria Fernanda',
            'receipt_last_names' => 'Perez Ruiz',
            'receipt_email' => 'boleta@example.test',
            'terms_document_id' => $this->activeTerms()->id,
            'terms_accepted' => '1',
        ])->assertSessionHasNoErrors();
    }

    private function activeTerms(): LegalDocument
    {
        return LegalDocument::query()
            ->where('type', LegalDocumentType::Terms->value)
            ->where('active_slot', LegalDocumentType::Terms->value)
            ->firstOrFail();
    }
}
