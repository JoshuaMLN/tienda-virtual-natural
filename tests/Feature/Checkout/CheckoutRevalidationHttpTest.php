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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CheckoutRevalidationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_endpoint_requires_an_authenticated_verified_customer_and_all_checkout_middleware(): void
    {
        $payload = ['review_reference' => str_repeat('a', 64)];

        $this->post(route('checkout.revalidate'), $payload)
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->post(route('checkout.revalidate'), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('checkout.revalidate'), $payload)
            ->assertForbidden();

        $middleware = Route::getRoutes()->getByName('checkout.revalidate')?->gatherMiddleware() ?? [];
        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('customer', $middleware);
        $this->assertContains('verified', $middleware);
    }

    public function test_payment_stage_exposes_the_protected_form_reference_and_blocking_modal(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, $product);
        $reviewReference = session('checkout.draft.review.fingerprint');

        $this->get(route('checkout.index', ['paso' => 3]))
            ->assertOk()
            ->assertSee('data-checkout-revalidation-form', false)
            ->assertSee('data-checkout-revalidation-url="'.route('checkout.confirm').'"', false)
            ->assertSee('value="'.$reviewReference.'"', false)
            ->assertSee('data-checkout-idempotency-key', false)
            ->assertSee('Confirmar pedido y pagar')
            ->assertSee('data-checkout-revalidation-modal', false)
            ->assertSee('data-bs-backdrop="static"', false)
            ->assertSee('Aceptar cambios y continuar')
            ->assertSee('Volver al carrito');
    }

    public function test_unchanged_checkout_returns_ready_without_trusting_client_amounts(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, $product);
        $reviewReference = session('checkout.draft.review.fingerprint');

        $this->postJson(route('checkout.revalidate'), [
            'review_reference' => $reviewReference,
            'total_cents' => 1,
            'unit_price_cents' => 1,
        ])->assertOk()
            ->assertJsonPath('revalidation.status', 'unchanged')
            ->assertJsonPath('revalidation.requires_confirmation', false)
            ->assertJsonPath('revalidation.proposal_reference', session('checkout.draft.delivery_quote.fingerprint'))
            ->assertJsonPath('revalidation.current.amounts.total_cents', 5900)
            ->assertJsonPath('redirect_url', null);

        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_changed_checkout_returns_conflict_and_accepts_only_the_latest_proposal(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, $product);
        $reviewReference = session('checkout.draft.review.fingerprint');

        $product->update(['price' => '69.00']);
        $first = $this->postJson(route('checkout.revalidate'), [
            'review_reference' => $reviewReference,
        ])->assertStatus(409)
            ->assertJsonPath('revalidation.status', 'changed')
            ->assertJsonPath('revalidation.requires_confirmation', true)
            ->assertJsonPath('revalidation.current.amounts.total_cents', 6900)
            ->assertJsonFragment([
                'code' => 'product_price_changed',
                'previous' => 5900,
                'current' => 6900,
            ]);

        $product->update(['price' => '79.00']);
        $second = $this->postJson(route('checkout.revalidate'), [
            'review_reference' => $reviewReference,
            'accepted_proposal_reference' => $first->json('revalidation.proposal_reference'),
        ])->assertStatus(409)
            ->assertJsonPath('revalidation.status', 'changed')
            ->assertJsonPath('revalidation.current.amounts.total_cents', 7900);

        $this->assertNotSame(
            $first->json('revalidation.proposal_reference'),
            $second->json('revalidation.proposal_reference'),
        );
        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertSame(5900, session('checkout.draft.delivery_quote.amounts.total_cents'));

        $accepted = $this->postJson(route('checkout.revalidate'), [
            'review_reference' => $reviewReference,
            'accepted_proposal_reference' => $second->json('revalidation.proposal_reference'),
        ])->assertOk()
            ->assertJsonPath('revalidation.status', 'unchanged')
            ->assertJsonPath('revalidation.current.amounts.total_cents', 7900);

        $this->assertNotSame($reviewReference, $accepted->json('revalidation.review_reference'));
        $this->assertSame(7900, session('checkout.draft.delivery_quote.amounts.total_cents'));
        $this->assertSame(
            session('checkout.draft.delivery_quote.fingerprint'),
            session('checkout.draft.review.delivery_quote_reference'),
        );
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_tampered_proposal_and_outdated_review_references_cannot_advance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '59.00', 'stock' => 5]);
        $this->reviewPickup($user, $product);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $product->update(['price' => '79.00']);

        $this->postJson(route('checkout.revalidate'), [
            'review_reference' => $reviewReference,
            'accepted_proposal_reference' => str_repeat('a', 64),
        ])->assertStatus(409)
            ->assertJsonPath('revalidation.status', 'changed')
            ->assertJsonPath('revalidation.current.amounts.total_cents', 7900);

        $this->postJson(route('checkout.revalidate'), [
            'review_reference' => str_repeat('b', 64),
        ])->assertStatus(409)
            ->assertJsonPath('revalidation', null)
            ->assertJsonPath('reload_url', route('checkout.index'));

        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertSame(5900, session('checkout.draft.delivery_quote.amounts.total_cents'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_new_terms_block_confirmation_and_send_the_customer_back_to_the_terms_step(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $this->reviewPickup($user, $product);
        $reviewReference = session('checkout.draft.review.fingerprint');
        $admin = User::factory()->admin()->create();
        $documents = app(LegalDocumentService::class);
        $draft = $documents->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];
        $documents->publish($draft, $admin);

        $this->actingAs($user)
            ->postJson(route('checkout.revalidate'), [
                'review_reference' => $reviewReference,
            ])->assertStatus(422)
            ->assertJsonPath('revalidation.status', 'blocked')
            ->assertJsonPath('revalidation.requires_terms_acceptance', true)
            ->assertJsonPath('redirect_url', route('checkout.index', ['paso' => 2]))
            ->assertJsonFragment(['code' => 'terms_changed']);

        $this->assertSame($reviewReference, session('checkout.draft.review.fingerprint'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_missing_review_and_invalid_references_return_structured_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('checkout.revalidate'), [
                'review_reference' => str_repeat('a', 64),
            ])->assertStatus(422)
            ->assertJsonPath('revalidation', null)
            ->assertJsonPath('reload_url', route('checkout.index'));

        $response = $this->postJson(route('checkout.revalidate'), [
            'review_reference' => 'referencia-manipulada',
            'accepted_proposal_reference' => 'otra-referencia',
        ]);

        $this->assertSame(422, $response->status(), $response->getContent());
        $response->assertJsonValidationErrors([
            'review_reference',
            'accepted_proposal_reference',
        ]);

        $this->assertNoCheckoutDomainRecords();
    }

    private function reviewPickup(User $user, Product $product): void
    {
        $this->actingAs($user);
        app(CartService::class)->add($product, 1);
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
