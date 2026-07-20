<?php

namespace Tests\Feature\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cart\CartService;
use App\Support\Legal\LegalDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_checkout_displays_fiscal_options_active_legal_versions_and_private_cache_headers(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.test']);
        $this->withCart($user);
        $terms = $this->activeTerms();

        $this->assertTrue(config('session.encrypt'));

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Comprobante de pago')
            ->assertSee('value="receipt"', false)
            ->assertSee('value="invoice"', false)
            ->assertSee('Continuar al pago')
            ->assertSeeInOrder(['Etapa 1', 'Contacto y entrega', 'Etapa 2', 'Comprobante de pago', 'Etapa 3', 'Pago'])
            ->assertDontSee('version '.$terms->version)
            ->assertSee(route('shop.terms'), false)
            ->assertSee(route('shop.privacy'), false)
            ->assertSee('Esta aceptacion no autoriza comunicaciones publicitarias.')
            ->assertDontSee('Pagar con Culqi');
    }

    public function test_review_requires_an_authenticated_verified_customer(): void
    {
        $payload = $this->receiptPayload();

        $this->post(route('checkout.fiscal.store'), $payload)
            ->assertRedirect(route('login'));

        $this->post(route('checkout.review'), $payload)
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('checkout.fiscal.store'), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('checkout.review'), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('checkout.fiscal.store'), $payload)
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('checkout.review'), $payload)
            ->assertForbidden();
    }

    public function test_checkout_progress_cannot_skip_pending_steps_and_can_reopen_completed_steps(): void
    {
        $user = User::factory()->create();
        $this->withCart($user);

        $this->get(route('checkout.index', ['paso' => 3]))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 1
                && $form['max_step'] === 1);

        $this->preparePickupDraft($user, addCart: false);

        $this->get(route('checkout.index', ['paso' => 3]))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 2
                && $form['max_step'] === 2);

        $this->get(route('checkout.index', ['paso' => 1]))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 1
                && $form['max_step'] === 2);

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasNoErrors();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 3
                && $form['max_step'] === 3);

        $this->get(route('checkout.index', ['paso' => 2]))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 2
                && $form['max_step'] === 3);

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_customer_can_save_and_restore_receipt_or_invoice_data_without_accepting_terms(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);
        $receipt = $this->receiptPayload([
            'receipt_first_names' => 'Nombres persistentes',
            'receipt_last_names' => 'Apellidos persistentes',
        ]);
        unset($receipt['terms_document_id'], $receipt['terms_accepted']);

        $this->post(route('checkout.fiscal.store'), $receipt)
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'checkout-fiscal-saved')
            ->assertSessionHas('checkout.draft.fiscal', fn (array $fiscal): bool => $fiscal['document_type'] === 'receipt'
                && $fiscal['first_names'] === 'Nombres persistentes'
                && $fiscal['last_names'] === 'Apellidos persistentes');

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="Nombres persistentes"', false)
            ->assertSee('value="Apellidos persistentes"', false)
            ->assertDontSee('Revision completada');

        $invoice = $this->invoicePayload([
            'invoice_business_name' => 'Empresa Persistente SAC',
            'invoice_address' => 'Av. Persistente 123',
        ]);
        unset($invoice['terms_document_id'], $invoice['terms_accepted']);

        $this->post(route('checkout.fiscal.store'), $invoice)
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('checkout.draft.fiscal', fn (array $fiscal): bool => $fiscal['document_type'] === 'invoice'
                && $fiscal['business_name'] === 'Empresa Persistente SAC'
                && $fiscal['fiscal_address'] === 'Av. Persistente 123');

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="Empresa Persistente SAC"', false)
            ->assertSee('value="Av. Persistente 123"', false)
            ->assertDontSee('Revision completada');

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_customer_can_review_a_receipt_without_changing_profile_or_creating_an_order(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre del perfil',
            'email' => 'perfil@example.test',
            'phone' => '999999999',
        ]);
        $this->preparePickupDraft($user);

        $this->post(route('checkout.review'), $this->receiptPayload([
            'receipt_identity_document_number' => '12 345 678',
            'receipt_first_names' => '  Maria   Fernanda ',
            'receipt_last_names' => ' Perez   Ruiz ',
            'receipt_email' => 'COMPRAS@EXAMPLE.TEST',
        ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('status', 'checkout-reviewed')
            ->assertSessionHas('checkout.draft', function (array $draft) use ($user): bool {
                $review = $draft['review'] ?? null;
                $fiscal = $review['fiscal'] ?? null;

                return is_array($review)
                    && $review['user_id'] === $user->id
                    && $review['customer_email'] === $user->email
                    && $review['terms_document_id'] === $this->activeTerms()->id
                    && $review['terms_document_version'] === $this->activeTerms()->version
                    && preg_match('/^[a-f0-9]{64}$/', $review['fingerprint']) === 1
                    && is_string($review['reviewed_at'])
                    && is_array($fiscal)
                    && $fiscal['document_type'] === 'receipt'
                    && $fiscal['identity_document_type'] === 'dni'
                    && $fiscal['identity_document_number'] === '12345678'
                    && $fiscal['first_names'] === 'Maria Fernanda'
                    && $fiscal['last_names'] === 'Perez Ruiz'
                    && $fiscal['business_name'] === null
                    && $fiscal['email'] === 'compras@example.test';
            });

        $this->assertSame('Nombre del perfil', $user->fresh()->name);
        $this->assertSame('perfil@example.test', $user->email);
        $this->assertSame('999999999', $user->phone);
        $this->assertNoCheckoutDomainRecords();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['active_step'] === 3
                && $form['max_step'] === 3)
            ->assertSee('Revision completada')
            ->assertSee('Actualizar y continuar')
            ->assertSee('Todavia no se genero ningun pedido ni se realizo ningun cobro.');
    }

    public function test_customer_can_review_an_invoice_with_a_valid_ruc(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);

        $this->post(route('checkout.review'), $this->invoicePayload([
            'invoice_ruc' => '20-13131295-5',
            'invoice_business_name' => '  Empresa   Natural SAC ',
            'invoice_address' => ' Av.   Empresa 456, San Isidro ',
            'invoice_email' => 'FACTURAS@EXAMPLE.TEST',
        ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('checkout.draft.review.fiscal', fn (array $fiscal): bool => $fiscal['document_type'] === 'invoice'
                && $fiscal['identity_document_type'] === 'ruc'
                && $fiscal['identity_document_number'] === '20131312955'
                && $fiscal['business_name'] === 'Empresa Natural SAC'
                && $fiscal['fiscal_address'] === 'Av. Empresa 456, San Isidro'
                && $fiscal['first_names'] === null
                && $fiscal['last_names'] === null
                && $fiscal['email'] === 'facturas@example.test');

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_receipt_accepts_supported_foreign_documents(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);

        $this->post(route('checkout.review'), $this->receiptPayload([
            'receipt_identity_document_type' => 'foreigner_card',
            'receipt_identity_document_number' => 'ce-001234567',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            'CE001234567',
            session('checkout.draft.review.fiscal.identity_document_number'),
        );

        $this->post(route('checkout.review'), $this->receiptPayload([
            'receipt_identity_document_type' => 'passport',
            'receipt_identity_document_number' => 'pa-123456',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            'PA123456',
            session('checkout.draft.review.fiscal.identity_document_number'),
        );
    }

    public function test_invalid_dni_and_ruc_checksum_are_rejected_and_input_is_preserved(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);

        $this->from(route('checkout.index'))
            ->post(route('checkout.review'), $this->receiptPayload([
                'receipt_identity_document_number' => '1234567',
                'receipt_first_names' => 'Nombre conservado',
            ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['receipt_identity_document_number'], null, 'checkoutReview')
            ->assertSessionHasInput('receipt_first_names', 'Nombre conservado');

        $this->from(route('checkout.index'))
            ->post(route('checkout.review'), $this->invoicePayload([
                'invoice_ruc' => '20131312954',
                'invoice_business_name' => 'Empresa conservada SAC',
            ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['invoice_ruc'], null, 'checkoutReview')
            ->assertSessionHasInput('invoice_business_name', 'Empresa conservada SAC');

        $this->assertNull(session('checkout.draft.review'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_hidden_fields_from_an_incompatible_document_type_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);

        $this->post(route('checkout.review'), $this->receiptPayload([
            'invoice_ruc' => '20131312955',
            'invoice_business_name' => 'Dato manipulado',
        ]))->assertSessionHasErrors(['fiscal_document_type'], null, 'checkoutReview');

        $this->post(route('checkout.review'), $this->invoicePayload([
            'receipt_identity_document_type' => 'dni',
            'receipt_identity_document_number' => '12345678',
            'receipt_first_names' => 'Dato',
        ]))->assertSessionHasErrors(['fiscal_document_type'], null, 'checkoutReview');

        $this->assertNull(session('checkout.draft.review'));
    }

    public function test_review_requires_saved_delivery_and_explicit_current_terms(): void
    {
        $user = User::factory()->create();
        $this->withCart($user);

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasErrors(['review'], null, 'checkoutReview');

        $this->preparePickupDraft($user, addCart: false);

        $payload = $this->receiptPayload();
        unset($payload['terms_accepted']);

        $this->post(route('checkout.review'), $payload)
            ->assertSessionHasErrors(['terms_accepted'], null, 'checkoutReview');

        $oldTerms = $this->activeTerms();
        $admin = User::factory()->admin()->create();
        $documents = app(LegalDocumentService::class);
        $draft = $documents->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];
        $documents->publish($draft, $admin);

        $this->actingAs($user)
            ->post(route('checkout.review'), $this->receiptPayload([
                'terms_document_id' => $oldTerms->id,
            ]))
            ->assertSessionHasErrors(['terms_document_id'], null, 'checkoutReview');

        $this->assertNull(session('checkout.draft.review'));
    }

    public function test_review_recalculates_a_changed_quote_and_requires_a_second_confirmation(): void
    {
        $user = User::factory()->create();
        $product = $this->preparePickupDraft($user);
        $oldReference = session('checkout.draft.delivery_quote.fingerprint');
        $product->update(['price' => '79.00']);

        $this->from(route('checkout.index'))
            ->post(route('checkout.review'), $this->receiptPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['review'], null, 'checkoutReview')
            ->assertSessionHasInput('receipt_first_names', 'Maria Fernanda');

        $this->assertNull(session('checkout.draft.review'));
        $this->assertSame(
            'Maria Fernanda',
            session('checkout.draft.fiscal.first_names'),
        );
        $this->assertNotSame($oldReference, session('checkout.draft.delivery_quote.fingerprint'));
        $this->assertSame(7900, session('checkout.draft.delivery_quote.amounts.total_cents'));

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'checkout-reviewed');

        $this->assertNoCheckoutDomainRecords();
    }

    public function test_a_new_terms_version_or_tampered_session_invalidates_the_review(): void
    {
        $user = User::factory()->create();
        $this->preparePickupDraft($user);
        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasNoErrors();

        $admin = User::factory()->admin()->create();
        $documents = app(LegalDocumentService::class);
        $draft = $documents->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];
        $currentTerms = $documents->publish($draft, $admin);

        $this->actingAs($user)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('version '.$currentTerms->version)
            ->assertSee('Continuar al pago')
            ->assertDontSee('Revision completada');

        $this->post(route('checkout.review'), $this->receiptPayload())
            ->assertSessionHasNoErrors();
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Revision completada');

        $sessionDraft = session('checkout.draft');
        $sessionDraft['review']['fiscal']['identity_document_number'] = '87654321';
        session()->put('checkout.draft', $sessionDraft);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('Revision completada');

        $this->assertNoCheckoutDomainRecords();
    }

    /** @param array<string, mixed> $overrides */
    private function receiptPayload(array $overrides = []): array
    {
        return array_merge([
            'fiscal_document_type' => 'receipt',
            'receipt_identity_document_type' => 'dni',
            'receipt_identity_document_number' => '12345678',
            'receipt_first_names' => 'Maria Fernanda',
            'receipt_last_names' => 'Perez Ruiz',
            'receipt_email' => 'boleta@example.test',
            'terms_document_id' => $this->activeTerms()->id,
            'terms_accepted' => '1',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'fiscal_document_type' => 'invoice',
            'invoice_ruc' => '20131312955',
            'invoice_business_name' => 'Empresa Natural SAC',
            'invoice_address' => 'Av. Empresa 456, San Isidro',
            'invoice_email' => 'facturas@example.test',
            'terms_document_id' => $this->activeTerms()->id,
            'terms_accepted' => '1',
        ], $overrides);
    }

    private function preparePickupDraft(User $user, bool $addCart = true): Product
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $this->actingAs($user);
        $product = $addCart
            ? $this->withCart($user)
            : Product::query()->firstOrFail();
        $quote = $this->postJson(route('checkout.delivery.quote'), [
            'delivery_method' => DeliveryMethod::Pickup->value,
        ])->assertOk()->json('delivery.quote_reference');

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto de compra',
            'contact_phone' => '987654321',
            'delivery_method' => DeliveryMethod::Pickup->value,
            'quote_reference' => $quote,
        ])->assertRedirect(route('checkout.index'));

        return $product;
    }

    private function withCart(User $user): Product
    {
        $this->actingAs($user);
        $product = Product::factory()->create([
            'price' => '59.00',
            'stock' => 10,
        ]);
        app(CartService::class)->add($product, 1);

        return $product;
    }

    private function activeTerms(): LegalDocument
    {
        return LegalDocument::query()
            ->where('type', LegalDocumentType::Terms->value)
            ->where('active_slot', LegalDocumentType::Terms->value)
            ->firstOrFail();
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
