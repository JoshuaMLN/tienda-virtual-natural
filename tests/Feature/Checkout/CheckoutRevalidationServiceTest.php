<?php

namespace Tests\Feature\Checkout;

use App\Enums\CheckoutChangeType;
use App\Enums\CheckoutRevalidationStatus;
use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Enums\TaxAffectation;
use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Checkout\CheckoutRevalidationException;
use App\Support\Checkout\CheckoutRevalidationResult;
use App\Support\Checkout\CheckoutRevalidationService;
use App\Support\Legal\LegalDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutRevalidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-07-21 10:00:00');
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_an_unchanged_review_returns_a_stable_result_without_side_effects(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 10]);
        $this->reviewPickup($user, [[$product, 2]]);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $deliveryReference = session('checkout.draft.delivery_quote.fingerprint');

        $result = $this->revalidator()->revalidate($user);

        $this->assertSame(CheckoutRevalidationStatus::Unchanged, $result->status);
        $this->assertTrue($result->canContinue());
        $this->assertFalse($result->requiresConfirmation());
        $this->assertFalse($result->requiresTermsAcceptance);
        $this->assertSame([], $result->changes);
        $this->assertSame([], $result->preservedCartItems);
        $this->assertSame($reviewReference, $result->reviewReference);
        $this->assertSame($deliveryReference, $result->current?->fingerprint());
        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertSame($deliveryReference, session('checkout.draft.delivery_quote.fingerprint'));
        $this->assertSame('unchanged', $result->toArray()['status']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_price_and_tax_changes_return_product_and_amount_differences_in_cents(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => '59.00',
            'stock' => 10,
            'tax_affectation' => TaxAffectation::Taxed,
        ]);
        $this->reviewPickup($user, [[$product, 1]]);

        $product->update([
            'price' => '79.00',
            'tax_affectation' => TaxAffectation::Exempt,
        ]);

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Changed, $result->status);
        $this->assertTrue($result->requiresConfirmation());
        $this->assertFalse($result->requiresTermsAcceptance);
        $this->assertSame(5900, $changes[CheckoutChangeType::ProductPriceChanged->value]['previous']);
        $this->assertSame(7900, $changes[CheckoutChangeType::ProductPriceChanged->value]['current']);
        $this->assertSame(
            ['tax_affectation' => 'taxed', 'tax_rate_bps' => 1800],
            $changes[CheckoutChangeType::ProductTaxChanged->value]['previous'],
        );
        $this->assertSame(
            ['tax_affectation' => 'exempt', 'tax_rate_bps' => 0],
            $changes[CheckoutChangeType::ProductTaxChanged->value]['current'],
        );
        $this->assertSame(5900, $changes[CheckoutChangeType::ProductsSubtotalChanged->value]['previous']);
        $this->assertSame(7900, $changes[CheckoutChangeType::ProductsSubtotalChanged->value]['current']);
        $this->assertSame(900, $changes[CheckoutChangeType::TaxChanged->value]['previous']);
        $this->assertSame(0, $changes[CheckoutChangeType::TaxChanged->value]['current']);
        $this->assertSame(7900, $result->current?->amounts['total_cents']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_stock_reduction_proposes_the_available_quantity_and_updates_only_the_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '20.00', 'stock' => 10]);
        $this->reviewPickup($user, [[$product, 4]]);
        $reviewReference = session('checkout.draft.review.fingerprint');

        $product->update(['stock' => 2]);

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Changed, $result->status);
        $this->assertSame(4, $changes[CheckoutChangeType::ProductQuantityReduced->value]['previous']);
        $this->assertSame(2, $changes[CheckoutChangeType::ProductQuantityReduced->value]['current']);
        $this->assertSame(2, $result->current?->items[0]['quantity']);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_hidden_and_out_of_stock_products_block_an_empty_proposal(): void
    {
        $user = User::factory()->create();
        $hidden = Product::factory()->create(['stock' => 5]);
        $outOfStock = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, [[$hidden, 1], [$outOfStock, 1]]);

        $hidden->update(['is_active' => false]);
        $outOfStock->update(['stock' => 0]);

        $result = $this->revalidator()->revalidate($user);
        $removed = collect($result->changes)
            ->filter(fn ($change): bool => $change->type === CheckoutChangeType::ProductRemoved)
            ->values();

        $this->assertSame(CheckoutRevalidationStatus::Blocked, $result->status);
        $this->assertFalse($result->canContinue());
        $this->assertNull($result->current);
        $this->assertCount(2, $removed);
        $this->assertEqualsCanonicalizing(
            [$hidden->id, $outOfStock->id],
            $removed->pluck('productId')->all(),
        );
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_removed_product_is_excluded_while_the_remaining_proposal_can_continue(): void
    {
        $user = User::factory()->create();
        $available = Product::factory()->create(['price' => '30.00', 'stock' => 5]);
        $removed = Product::factory()->create(['price' => '20.00', 'stock' => 5]);
        $this->reviewPickup($user, [[$available, 1], [$removed, 1]]);

        $removed->update(['published_at' => null]);

        $result = $this->revalidator()->revalidate($user);

        $this->assertSame(CheckoutRevalidationStatus::Changed, $result->status);
        $this->assertTrue($result->canContinue());
        $this->assertSame([$available->id], array_column($result->current?->items ?? [], 'product_id'));
        $this->assertContains(
            CheckoutChangeType::ProductRemoved,
            array_map(fn ($change) => $change->type, $result->changes),
        );
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_concurrent_cart_additions_and_extra_units_are_preserved_outside_the_proposal(): void
    {
        $user = User::factory()->create();
        $reviewed = Product::factory()->create(['price' => '40.00', 'stock' => 10]);
        $addedLater = Product::factory()->create(['price' => '25.00', 'stock' => 10]);
        $this->reviewPickup($user, [[$reviewed, 2]]);

        app(CartService::class)->add($reviewed, 1);
        app(CartService::class)->add($addedLater, 2);

        $result = $this->revalidator()->revalidate($user);

        $this->assertSame(CheckoutRevalidationStatus::Unchanged, $result->status);
        $this->assertSame(2, $result->current?->items[0]['quantity']);
        $this->assertEquals([
            [
                'product_id' => $reviewed->id,
                'product_name' => $reviewed->name,
                'quantity' => 1,
            ],
            [
                'product_id' => $addedLater->id,
                'product_name' => $addedLater->name,
                'quantity' => 2,
            ],
        ], $result->preservedCartItems);
        $this->assertDatabaseHas('cart_items', ['product_id' => $reviewed->id, 'quantity' => 3]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $addedLater->id, 'quantity' => 2]);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_home_delivery_tariff_change_recalculates_shipping_tax_and_total(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $district = $this->activeDistrict('11.80');
        $product = Product::factory()->create(['price' => '118.00', 'stock' => 5]);
        $this->reviewHomeDelivery($user, $address, [[$product, 1]]);

        $district->update(['shipping_fee' => '23.60']);

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Changed, $result->status);
        $this->assertSame(1180, $changes[CheckoutChangeType::DeliveryBaseFeeChanged->value]['previous']);
        $this->assertSame(2360, $changes[CheckoutChangeType::DeliveryBaseFeeChanged->value]['current']);
        $this->assertSame(1180, $changes[CheckoutChangeType::ShippingFeeChanged->value]['previous']);
        $this->assertSame(2360, $changes[CheckoutChangeType::ShippingFeeChanged->value]['current']);
        $this->assertSame(180, $changes[CheckoutChangeType::ShippingTaxChanged->value]['previous']);
        $this->assertSame(360, $changes[CheckoutChangeType::ShippingTaxChanged->value]['current']);
        $this->assertSame(12980, $changes[CheckoutChangeType::TotalChanged->value]['previous']);
        $this->assertSame(14160, $changes[CheckoutChangeType::TotalChanged->value]['current']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_product_added_after_review_does_not_change_the_reviewed_shipping_threshold(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '100.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $reviewed = Product::factory()->create(['price' => '90.00', 'stock' => 5]);
        $addedLater = Product::factory()->create(['price' => '20.00', 'stock' => 5]);
        $this->reviewHomeDelivery($user, $address, [[$reviewed, 1]]);

        app(CartService::class)->add($addedLater, 1);

        $result = $this->revalidator()->revalidate($user);

        $this->assertSame(CheckoutRevalidationStatus::Unchanged, $result->status);
        $this->assertFalse($result->current?->hasFreeShipping ?? true);
        $this->assertSame(1180, $result->current?->amounts['shipping_fee_cents']);
        $this->assertSame([$reviewed->id], array_column($result->current?->items ?? [], 'product_id'));
        $this->assertSame([
            [
                'product_id' => $addedLater->id,
                'product_name' => $addedLater->name,
                'quantity' => 1,
            ],
        ], $result->preservedCartItems);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_delivery_window_change_is_reported_without_blocking_confirmation(): void
    {
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN, 1);
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX, 1);
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);

        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN, 2);
        Setting::setValue(Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX, 3);

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Changed, $result->status);
        $this->assertTrue($result->canContinue());
        $this->assertSame(1, $changes[CheckoutChangeType::DeliveryEstimateChanged->value]['previous']['business_days_min']);
        $this->assertSame(2, $changes[CheckoutChangeType::DeliveryEstimateChanged->value]['current']['business_days_min']);
        $this->assertSame(3, $changes[CheckoutChangeType::DeliveryEstimateChanged->value]['current']['business_days_max']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_free_shipping_change_is_reported_without_trusting_the_previous_total(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '999.00');
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->activeDistrict('11.80');
        $product = Product::factory()->create(['price' => '118.00', 'stock' => 5]);
        $this->reviewHomeDelivery($user, $address, [[$product, 1]]);

        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '100.00');

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(false, $changes[CheckoutChangeType::FreeShippingChanged->value]['previous']);
        $this->assertSame(true, $changes[CheckoutChangeType::FreeShippingChanged->value]['current']);
        $this->assertSame(1180, $changes[CheckoutChangeType::ShippingFeeChanged->value]['previous']);
        $this->assertSame(0, $changes[CheckoutChangeType::ShippingFeeChanged->value]['current']);
        $this->assertSame(11800, $result->current?->amounts['total_cents']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_lost_delivery_coverage_returns_a_blocked_structured_change(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $district = $this->activeDistrict('11.80');
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewHomeDelivery($user, $address, [[$product, 1]]);

        $district->update(['is_active' => false]);

        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Blocked, $result->status);
        $this->assertFalse($result->canContinue());
        $this->assertNull($result->current);
        $this->assertSame(
            [
                'available' => true,
                'method' => 'home_delivery',
                'address_id' => $address->id,
                'ubigeo' => $address->ubigeo,
            ],
            $changes[CheckoutChangeType::DeliveryUnavailable->value]['previous'],
        );
        $this->assertSame(
            [
                'available' => false,
                'reason' => 'La entrega a domicilio no esta disponible para el distrito seleccionado.',
            ],
            $changes[CheckoutChangeType::DeliveryUnavailable->value]['current'],
        );
        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_new_terms_version_blocks_commercial_confirmation_until_reaccepted(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);
        $previousTermsId = session('checkout.draft.review.terms_document_id');
        $admin = User::factory()->admin()->create();
        $documents = app(LegalDocumentService::class);
        $draft = $documents->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];
        $currentTerms = $documents->publish($draft, $admin);

        $this->actingAs($user);
        $result = $this->revalidator()->revalidate($user);
        $changes = $this->changesByCode($result);

        $this->assertSame(CheckoutRevalidationStatus::Blocked, $result->status);
        $this->assertTrue($result->requiresTermsAcceptance);
        $this->assertNotNull($result->current);
        $this->assertSame($previousTermsId, $changes[CheckoutChangeType::TermsChanged->value]['previous']['document_id']);
        $this->assertSame($currentTerms->id, $changes[CheckoutChangeType::TermsChanged->value]['current']['document_id']);
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_revalidation_requires_a_review_owned_by_the_current_customer(): void
    {
        $user = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);
        $this->actingAs($otherCustomer);

        $this->expectException(CheckoutRevalidationException::class);
        $this->expectExceptionMessage('El checkout no tiene una revision vigente.');

        $this->revalidator()->revalidate($otherCustomer);
    }

    public function test_accepting_the_exact_proposal_replaces_quote_and_review_without_creating_an_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);
        $previousReviewReference = session('checkout.draft.review.fingerprint');

        $product->update(['price' => '79.00']);
        $proposal = $this->revalidator()->revalidate($user, $previousReviewReference);
        $accepted = $this->revalidator()->accept(
            $user,
            $previousReviewReference,
            $proposal->current?->fingerprint() ?? '',
        );

        $this->assertSame(CheckoutRevalidationStatus::Unchanged, $accepted->status);
        $this->assertFalse($accepted->requiresConfirmation());
        $this->assertSame(7900, $accepted->previous->amounts['total_cents']);
        $this->assertSame(7900, session('checkout.draft.delivery_quote.amounts.total_cents'));
        $this->assertNotSame($previousReviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertSame(
            session('checkout.draft.delivery_quote.fingerprint'),
            session('checkout.draft.review.delivery_quote_reference'),
        );
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_accepting_a_stale_proposal_returns_the_new_conflict_and_keeps_the_review_untouched(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);
        $reviewReference = session('checkout.draft.review.fingerprint');

        $product->update(['price' => '69.00']);
        $firstProposal = $this->revalidator()->revalidate($user, $reviewReference);
        $product->update(['price' => '79.00']);

        $secondProposal = $this->revalidator()->accept(
            $user,
            $reviewReference,
            $firstProposal->current?->fingerprint() ?? '',
        );

        $this->assertSame(CheckoutRevalidationStatus::Changed, $secondProposal->status);
        $this->assertSame(7900, $secondProposal->current?->amounts['total_cents']);
        $this->assertNotSame(
            $firstProposal->current?->fingerprint(),
            $secondProposal->current?->fingerprint(),
        );
        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertSame(5900, session('checkout.draft.delivery_quote.amounts.total_cents'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_revalidation_rejects_an_outdated_review_reference(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, [[$product, 1]]);

        try {
            $this->revalidator()->revalidate($user, str_repeat('a', 64));
            $this->fail('Se esperaba rechazar la referencia de revision obsoleta.');
        } catch (CheckoutRevalidationException $exception) {
            $this->assertSame(409, $exception->httpStatus);
            $this->assertStringContainsString('ya no esta vigente', $exception->getMessage());
        }

        $this->assertNoCheckoutDomainRecords();
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function reviewPickup(User $user, array $lines): void
    {
        $this->actingAs($user);
        $this->addLines($lines);
        $quote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])->assertOk()->json('delivery.quote_reference');

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto de compra',
            'contact_phone' => '987654321',
            'delivery_method' => DeliveryMethod::Pickup->value,
            'quote_reference' => $quote,
        ])->assertSessionHasNoErrors();

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasNoErrors();
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function reviewHomeDelivery(User $user, CustomerAddress $address, array $lines): void
    {
        $this->actingAs($user);
        $this->addLines($lines);
        $quote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_id' => $address->id,
        ])->assertOk()->json('delivery.quote_reference');

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto de compra',
            'contact_phone' => '987654321',
            'delivery_method' => DeliveryMethod::HomeDelivery->value,
            'address_choice' => 'address:'.$address->id,
            'quote_reference' => $quote,
        ])->assertSessionHasNoErrors();

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasNoErrors();
    }

    /** @param list<array{0: Product, 1: int}> $lines */
    private function addLines(array $lines): void
    {
        foreach ($lines as [$product, $quantity]) {
            app(CartService::class)->add($product, $quantity);
        }
    }

    /** @return array<string, mixed> */
    private function receiptPayload(): array
    {
        return [
            'fiscal_document_type' => 'receipt',
            'receipt_identity_document_type' => 'dni',
            'receipt_identity_document_number' => '12345678',
            'receipt_first_names' => 'Maria Fernanda',
            'receipt_last_names' => 'Perez Ruiz',
            'receipt_email' => 'boleta@example.test',
            'terms_document_id' => $this->activeTerms()->id,
            'terms_accepted' => '1',
        ];
    }

    private function activeTerms(): LegalDocument
    {
        return LegalDocument::query()
            ->where('type', LegalDocumentType::Terms->value)
            ->where('active_slot', LegalDocumentType::Terms->value)
            ->firstOrFail();
    }

    private function activeDistrict(string $shippingFee): DeliveryDistrict
    {
        return DeliveryDistrict::factory()->create([
            'ubigeo' => '150140',
            'province_code' => '1501',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'shipping_fee' => $shippingFee,
            'is_active' => true,
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function changesByCode(CheckoutRevalidationResult $result): array
    {
        return collect($result->changes)
            ->mapWithKeys(fn ($change): array => [$change->type->value => $change->toArray()])
            ->all();
    }

    private function revalidator(): CheckoutRevalidationService
    {
        return app(CheckoutRevalidationService::class);
    }

    private function assertNoCheckoutDomainRecords(): void
    {
        $this->assertDatabaseCount('order_sequences', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertDatabaseCount('fiscal_document_deliveries', 0);
    }
}
