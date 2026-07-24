<?php

namespace Tests\Feature\Account;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerFiscalDocumentHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.locale', 'es');
        config()->set('app.timezone', 'America/Lima');
        CarbonImmutable::setTestNow('2026-07-24 10:00:00', 'America/Lima');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_pending_order_does_not_show_a_fictitious_fiscal_document_section(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 1, paid: false);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertDontSee('Documentos fiscales')
            ->assertDontSee('Comprobante pendiente de emision');
    }

    public function test_paid_order_without_documents_shows_the_pending_issue_state(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 2);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Documentos fiscales')
            ->assertSee('Comprobante pendiente de emision')
            ->assertSee('Lo encontraras aqui cuando la tienda registre el documento emitido.')
            ->assertDontSee('Descargar PDF');
    }

    public function test_detail_lists_sale_and_related_documents_including_annulled_documents(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 3);
        $sale = $this->document($order, [
            'type' => FiscalDocumentType::Invoice,
            'series' => 'F001',
            'correlative' => '00000042',
            'issued_at' => CarbonImmutable::parse('2026-07-23 16:15:00', 'America/Lima'),
            'pdf_path' => 'fiscal/private/invoice-42.pdf',
            'xml_path' => 'fiscal/private/invoice-42.xml',
        ]);
        $creditNote = $this->document($order, [
            'parent_document_id' => $sale->id,
            'type' => FiscalDocumentType::CreditNote,
            'sale_document_slot' => null,
            'series' => 'FC01',
            'correlative' => '00000007',
            'issued_at' => CarbonImmutable::parse('2026-07-24 09:30:00', 'America/Lima'),
            'status' => FiscalDocumentStatus::Annulled,
            'pdf_path' => 'fiscal/private/credit-note-7.pdf',
            'annulled_at' => now(),
            'annulment_reason' => 'Correccion administrativa',
        ]);

        $response = $this->actingAs($customer)
            ->get(route('account.orders.show', $order->code));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Documentos fiscales')
            ->assertSee('Factura')
            ->assertSee('F001-00000042')
            ->assertSee('Emitido')
            ->assertSee('23 de julio de 2026 a las 04:15 p. m.')
            ->assertSee('Nota de credito')
            ->assertSee('FC01-00000007')
            ->assertSee('Anulado')
            ->assertSee('24 de julio de 2026 a las 09:30 a. m.')
            ->assertSee(route('account.orders.fiscal-documents.download', [
                'code' => $order->code,
                'document' => $sale->id,
            ]), false)
            ->assertSee(route('account.orders.fiscal-documents.download', [
                'code' => $order->code,
                'document' => $creditNote->id,
            ]), false)
            ->assertDontSee('fiscal/private/invoice-42.pdf')
            ->assertDontSee('fiscal/private/invoice-42.xml')
            ->assertDontSee('Correccion administrativa');
    }

    public function test_owner_can_download_its_private_pdf_with_safe_headers_and_name(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 4);
        $document = $this->document($order, [
            'type' => FiscalDocumentType::Receipt,
            'series' => 'B001',
            'correlative' => '00000124',
            'pdf_path' => 'fiscal/orders/receipt-124.pdf',
        ]);
        Storage::disk('local')->put($document->pdf_path, '%PDF-1.7 private receipt');

        $response = $this->actingAs($customer)
            ->get(route('account.orders.fiscal-documents.download', [
                'code' => $order->code,
                'document' => $document->id,
            ]));

        $response->assertOk()
            ->assertDownload('boleta-B001-00000124.pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('Expires', '0')
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_annulled_document_remains_downloadable_for_its_owner(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 5);
        $document = $this->document($order, [
            'status' => FiscalDocumentStatus::Annulled,
            'series' => 'B002',
            'correlative' => '00000008',
            'pdf_path' => 'fiscal/orders/annulled-receipt.pdf',
            'annulled_at' => now(),
            'annulment_reason' => 'Documento reemplazado',
        ]);
        Storage::disk('local')->put($document->pdf_path, '%PDF-1.7 annulled receipt');

        $this->actingAs($customer)
            ->get(route('account.orders.fiscal-documents.download', [
                'code' => $order->code,
                'document' => $document->id,
            ]))
            ->assertOk()
            ->assertDownload('boleta-B002-00000008.pdf');
    }

    public function test_download_rejects_foreign_orders_and_mismatched_order_document_pairs_with_404(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $ownOrder = $this->order($customer, 6);
        $secondOwnOrder = $this->order($customer, 7);
        $foreignOrder = $this->order($otherCustomer, 8);
        $secondOwnDocument = $this->document($secondOwnOrder);
        $foreignDocument = $this->document($foreignOrder, [
            'series' => 'B009',
            'correlative' => '00000009',
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.fiscal-documents.download', [
                'code' => $ownOrder->code,
                'document' => $secondOwnDocument->id,
            ]))
            ->assertNotFound();

        $this->get(route('account.orders.fiscal-documents.download', [
            'code' => $foreignOrder->code,
            'document' => $foreignDocument->id,
        ]))->assertNotFound();

        $this->get(route('account.orders.fiscal-documents.download', [
            'code' => $ownOrder->code,
            'document' => $foreignDocument->id,
        ]))->assertNotFound();

        $this->get(route('account.orders.fiscal-documents.download', [
            'code' => $ownOrder->code,
            'document' => 999_999,
        ]))->assertNotFound();
    }

    public function test_download_rejects_missing_blank_and_non_pdf_files_with_the_same_404(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 9);
        $missing = $this->document($order, [
            'series' => 'B010',
            'correlative' => '00000010',
            'pdf_path' => 'fiscal/orders/missing.pdf',
        ]);
        $blank = $this->document($order, [
            'type' => FiscalDocumentType::CreditNote,
            'sale_document_slot' => null,
            'series' => 'BC01',
            'correlative' => '00000011',
            'pdf_path' => '',
        ]);
        $notPdf = $this->document($order, [
            'type' => FiscalDocumentType::DebitNote,
            'sale_document_slot' => null,
            'series' => 'BD01',
            'correlative' => '00000012',
            'pdf_path' => 'fiscal/orders/not-a-pdf.txt',
        ]);
        Storage::disk('local')->put($notPdf->pdf_path, 'not a pdf');

        foreach ([$missing, $blank, $notPdf] as $document) {
            $this->actingAs($customer)
                ->get(route('account.orders.fiscal-documents.download', [
                    'code' => $order->code,
                    'document' => $document->id,
                ]))
                ->assertNotFound();
        }
    }

    public function test_guest_is_redirected_and_non_numeric_document_route_is_not_resolved(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 10);
        $document = $this->document($order);

        $this->get(route('account.orders.fiscal-documents.download', [
            'code' => $order->code,
            'document' => $document->id,
        ]))->assertRedirect(route('login'));

        $this->actingAs($customer)
            ->get("/mi-cuenta/pedidos/{$order->code}/comprobantes/no-es-un-id/descargar")
            ->assertNotFound();
    }

    private function order(User $customer, int $sequence, bool $paid = true): Order
    {
        $factory = Order::factory()->for($customer);

        if ($paid) {
            $factory = $factory->paid();
        }

        return $factory->create([
            'code' => sprintf('PED-2026-%06d', $sequence),
            'sequence_year' => 2026,
            'sequence_number' => $sequence,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function document(Order $order, array $attributes = []): FiscalDocument
    {
        return FiscalDocument::factory()->for($order)->create(array_merge([
            'series' => 'B001',
            'correlative' => str_pad((string) $order->sequence_number, 8, '0', STR_PAD_LEFT),
            'pdf_path' => "fiscal/orders/{$order->code}.pdf",
        ], $attributes));
    }
}
