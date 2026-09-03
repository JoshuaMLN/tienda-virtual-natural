<?php

namespace Tests\Feature;

use App\Enums\FiscalDocumentType;
use App\Enums\OrderHistoryDomain;
use App\Enums\PaymentStatus;
use App\Jobs\SendFiscalDocumentEmail;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminFiscalDocumentHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create();
        $this->actingAs($this->adminUser);
        Storage::fake('local');
        CarbonImmutable::setTestNow('2026-08-02 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_admin_registers_the_requested_document_and_can_download_its_private_pdf(): void
    {
        $order = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);

        $this->post(route('admin.orders.fiscal-documents.store', $order->code), [
            'series' => ' b001 ',
            'correlative' => ' 00000012 ',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('boleta.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('success');

        $document = FiscalDocument::query()->sole();

        $this->assertSame($order->id, $document->order_id);
        $this->assertSame(FiscalDocumentType::Receipt, $document->type);
        $this->assertSame('B001', $document->series);
        $this->assertSame('00000012', $document->correlative);
        $this->assertSame('2026-08-01', $document->issued_at->toDateString());
        $this->assertSame($this->adminUser->id, $document->registered_by);
        Storage::disk('local')->assertExists($document->pdf_path);

        $this->get(route('admin.orders.fiscal-documents.download', [
            'order' => $order->code,
            'document' => $document,
        ]))->assertOk()
            ->assertDownload('boleta-B001-00000012.pdf');
    }

    public function test_registration_is_only_available_for_paid_orders_and_discards_the_uploaded_file(): void
    {
        $order = Order::factory()->create();

        $this->post(route('admin.orders.fiscal-documents.store', $order->code), [
            'series' => 'B001',
            'correlative' => '00000012',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('boleta.pdf', 120, 'application/pdf'),
        ])->assertRedirect()
            ->assertSessionHas('error', 'Solo un pedido pagado puede recibir un comprobante fiscal.');

        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('fiscal-documents'));
    }

    public function test_registration_rejects_future_dates_and_files_other_than_pdf(): void
    {
        $order = Order::factory()->paid()->create();

        $this->from(route('admin.orders.show', $order->code))
            ->post(route('admin.orders.fiscal-documents.store', $order->code), [
                'series' => 'B001',
                'correlative' => '00000012',
                'issued_at' => '2026-08-03',
                'pdf' => UploadedFile::fake()->image('boleta.png'),
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHasErrors(['issued_at', 'pdf']);

        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_customer_cannot_register_a_fiscal_document(): void
    {
        $order = Order::factory()->paid()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.fiscal-documents.store', $order->code), [
                'series' => 'B001',
                'correlative' => '00000012',
                'issued_at' => '2026-08-01',
                'pdf' => UploadedFile::fake()->create('boleta.pdf', 120, 'application/pdf'),
            ])->assertForbidden();

        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_order_detail_exposes_the_locked_type_and_warns_about_a_date_different_from_payment(): void
    {
        $order = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Invoice,
        ]);
        OrderStatusHistory::factory()->for($order)->create([
            'domain' => OrderHistoryDomain::Payment,
            'from_status' => PaymentStatus::Pending->value,
            'to_status' => PaymentStatus::Paid->value,
            'created_at' => CarbonImmutable::parse('2026-08-01 10:00:00'),
        ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Comprobante pendiente de emision.')
            ->assertSee('Registrar comprobante emitido en SUNAT')
            ->assertSee('Tipo solicitado: <strong>Factura</strong>.', false)
            ->assertDontSee('name="fiscal_document_type"', false);

        FiscalDocument::factory()->for($order)->create([
            'type' => FiscalDocumentType::Invoice,
            'issued_at' => CarbonImmutable::parse('2026-08-02 09:00:00'),
        ]);

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('La fecha fiscal difiere de la confirmacion del pago. Revisa la incidencia con contabilidad.')
            ->assertDontSee('Registrar comprobante emitido en SUNAT');
    }

    public function test_admin_cannot_download_a_document_from_another_order(): void
    {
        $document = FiscalDocument::factory()->create();
        $otherOrder = Order::factory()->paid()->create();

        $this->get(route('admin.orders.fiscal-documents.download', [
            'order' => $otherOrder->code,
            'document' => $document,
        ]))->assertNotFound();
    }

    public function test_admin_can_correct_a_file_and_registration_together_with_audit_snapshots(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create([
            'series' => 'B001',
            'correlative' => '00000010',
            'issued_at' => CarbonImmutable::parse('2026-08-01'),
            'pdf_path' => 'fiscal-documents/original.pdf',
        ]);

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
                'series' => ' b002 ',
                'correlative' => ' 00000011 ',
                'issued_at' => '2026-08-02',
                'reason' => 'Se adjunto el PDF correcto y se corrigio el correlativo.',
                'pdf' => UploadedFile::fake()->create('boleta-corregida.pdf', 120, 'application/pdf'),
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('success', 'Comprobante corregido con historial privado.');

        $document->refresh();
        $this->assertNotSame('fiscal-documents/original.pdf', $document->pdf_path);
        Storage::disk('local')->assertExists($document->pdf_path);
        $this->assertSame('B002', $document->series);
        $this->assertSame('00000011', $document->correlative);
        $this->assertDatabaseHas('fiscal_document_file_versions', [
            'fiscal_document_id' => $document->id,
            'version' => 1,
            'pdf_path' => 'fiscal-documents/original.pdf',
            'reason' => 'Se adjunto el PDF correcto y se corrigio el correlativo.',
            'replaced_by' => $this->adminUser->id,
            'replaced_by_name' => $this->adminUser->name,
        ]);
        $this->assertDatabaseHas('fiscal_document_corrections', [
            'fiscal_document_id' => $document->id,
            'reason' => 'Se adjunto el PDF correcto y se corrigio el correlativo.',
            'corrected_by' => $this->adminUser->id,
            'corrected_by_name' => $this->adminUser->name,
        ]);
    }

    public function test_correction_requests_validate_input_and_annulled_documents_cannot_be_corrected(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
                'series' => 'B001',
                'correlative' => '00000099',
                'issued_at' => '2026-08-01',
                'reason' => '',
                'pdf' => UploadedFile::fake()->image('no-es-pdf.png'),
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHasErrors(['reason', 'pdf']);

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
                'series' => 'B001',
                'correlative' => '00000099',
                'issued_at' => '2026-08-03',
                'reason' => '',
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHasErrors(['issued_at', 'reason']);

        app(FiscalDocumentService::class)->annul($document, 'Anulado externamente', $this->adminUser);

        $this->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
            'reason' => 'Intento posterior a la anulacion.',
            'pdf' => UploadedFile::fake()->create('no-debe-conservarse.pdf', 120, 'application/pdf'),
            'series' => 'B001',
            'correlative' => '00000099',
            'issued_at' => '2026-08-01',
        ])->assertRedirect()
            ->assertSessionHas('error', 'Solo se puede corregir un comprobante fiscal emitido.');

        $this->assertDatabaseCount('fiscal_document_file_versions', 0);
        $this->assertDatabaseCount('fiscal_document_corrections', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('fiscal-documents'));
    }

    public function test_admin_correction_requires_an_actual_pdf_or_registration_change(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create([
            'series' => 'B001',
            'correlative' => '00000010',
            'issued_at' => CarbonImmutable::parse('2026-08-01'),
        ]);

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
                'series' => 'B001',
                'correlative' => '00000010',
                'issued_at' => '2026-08-01',
                'reason' => 'No hay diferencia real.',
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('error', 'La correccion no contiene cambios.');

        $this->assertDatabaseCount('fiscal_document_file_versions', 0);
        $this->assertDatabaseCount('fiscal_document_corrections', 0);
    }

    public function test_admin_can_register_an_annulment_with_an_actor_snapshot(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();

        $this->from(route('admin.orders.show', $order->code))
            ->patch(route('admin.orders.fiscal-documents.annul', [$order->code, $document]), [
                'reason' => 'Anulado externamente en SUNAT.',
            ])->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('success', 'Anulacion fiscal registrada.');

        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $document->id,
            'status' => 'annulled',
            'annulment_reason' => 'Anulado externamente en SUNAT.',
            'annulled_by' => $this->adminUser->id,
            'annulled_by_name' => $this->adminUser->name,
        ]);
    }

    public function test_admin_can_register_a_related_note_but_not_on_an_annulled_document(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();

        $this->post(route('admin.orders.fiscal-documents.related.store', [$order->code, $document]), [
            'type' => FiscalDocumentType::CreditNote->value,
            'series' => 'BC01',
            'correlative' => '00000001',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('nota.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('fiscal_documents', ['parent_document_id' => $document->id, 'type' => FiscalDocumentType::CreditNote->value]);
        app(FiscalDocumentService::class)->annul($document, 'Anulado externamente', $this->adminUser);

        $this->post(route('admin.orders.fiscal-documents.related.store', [$order->code, $document]), [
            'type' => FiscalDocumentType::DebitNote->value,
            'series' => 'BD01',
            'correlative' => '00000001',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('nota.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_admin_can_register_only_one_replacement_for_an_annulled_document(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();
        app(FiscalDocumentService::class)->annul($document, 'Anulado externamente', $this->adminUser);
        $payload = ['series' => 'B001', 'correlative' => '00000090', 'issued_at' => '2026-08-01', 'pdf' => UploadedFile::fake()->create('reemplazo.pdf', 120, 'application/pdf')];

        $this->post(route('admin.orders.fiscal-documents.replacement.store', [$order->code, $document]), $payload)
            ->assertRedirect()->assertSessionHas('success');

        $storedFiles = Storage::disk('local')->allFiles('fiscal-documents');
        $payload['pdf'] = UploadedFile::fake()->create('otro.pdf', 120, 'application/pdf');
        $this->post(route('admin.orders.fiscal-documents.replacement.store', [$order->code, $document]), $payload)
            ->assertRedirect()->assertSessionHas('error', 'El comprobante ya tiene un reemplazo vigente.');

        $this->assertSame($storedFiles, Storage::disk('local')->allFiles('fiscal-documents'));
    }

    public function test_fiscal_mutation_routes_hide_documents_from_another_order(): void
    {
        $document = FiscalDocument::factory()->create();
        $otherOrder = Order::factory()->paid()->create();

        $this->patch(route('admin.orders.fiscal-documents.correct', [$otherOrder->code, $document]), [
            'series' => 'B001',
            'correlative' => '00000009',
            'issued_at' => '2026-08-01',
            'reason' => 'No corresponde',
            'pdf' => UploadedFile::fake()->create('boleta.pdf', 120, 'application/pdf'),
        ])->assertNotFound();
        $this->patch(route('admin.orders.fiscal-documents.annul', [$otherOrder->code, $document]), ['reason' => 'No corresponde'])
            ->assertNotFound();
        $this->post(route('admin.orders.fiscal-documents.related.store', [$otherOrder->code, $document]), [
            'type' => FiscalDocumentType::CreditNote->value,
            'series' => 'BC01',
            'correlative' => '00000009',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('nota.pdf', 120, 'application/pdf'),
        ])
            ->assertNotFound();
        $this->post(route('admin.orders.fiscal-documents.replacement.store', [$otherOrder->code, $document]), [
            'series' => 'B001',
            'correlative' => '00000010',
            'issued_at' => '2026-08-01',
            'pdf' => UploadedFile::fake()->create('reemplazo.pdf', 120, 'application/pdf'),
        ])->assertNotFound();
        $this->post(route('admin.orders.fiscal-documents.send', [$otherOrder->code, $document]))
            ->assertNotFound();

        $this->assertSame([], Storage::disk('local')->allFiles('fiscal-documents'));
    }

    public function test_customer_cannot_use_any_fiscal_mutation_route(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->patch(route('admin.orders.fiscal-documents.correct', [$order->code, $document]), [
                'series' => 'B001',
                'correlative' => '00000011',
                'issued_at' => '2026-08-01',
                'reason' => 'No autorizado',
                'pdf' => UploadedFile::fake()->create('boleta.pdf', 120, 'application/pdf'),
            ])->assertForbidden();
        $this->actingAs($customer)
            ->patch(route('admin.orders.fiscal-documents.annul', [$order->code, $document]), ['reason' => 'No autorizado'])
            ->assertForbidden();
        $this->actingAs($customer)
            ->post(route('admin.orders.fiscal-documents.related.store', [$order->code, $document]), [
                'type' => FiscalDocumentType::CreditNote->value,
                'series' => 'BC01',
                'correlative' => '00000001',
                'issued_at' => '2026-08-01',
                'pdf' => UploadedFile::fake()->create('nota.pdf', 120, 'application/pdf'),
            ])->assertForbidden();
        $this->actingAs($customer)
            ->post(route('admin.orders.fiscal-documents.replacement.store', [$order->code, $document]), [
                'series' => 'B001',
                'correlative' => '00000011',
                'issued_at' => '2026-08-01',
                'pdf' => UploadedFile::fake()->create('reemplazo.pdf', 120, 'application/pdf'),
            ])->assertForbidden();
        $this->actingAs($customer)
            ->post(route('admin.orders.fiscal-documents.send', [$order->code, $document]))
            ->assertForbidden();
    }

    public function test_admin_queues_a_current_issued_pdf_for_delivery_to_the_fiscal_email(): void
    {
        Queue::fake();
        $order = Order::factory()->paid()->create([
            'fiscal_email' => 'comprobantes@example.test',
        ]);
        $document = FiscalDocument::factory()->for($order)->create([
            'pdf_path' => 'fiscal-documents/current.pdf',
        ]);
        Storage::disk('local')->put($document->pdf_path, 'PDF de prueba');

        $this->get(route('admin.orders.show', $order->code))
            ->assertOk()
            ->assertSee('Enviar comprobante');

        $this->from(route('admin.orders.show', $order->code))
            ->post(route('admin.orders.fiscal-documents.send', [$order->code, $document]))
            ->assertRedirect(route('admin.orders.show', $order->code))
            ->assertSessionHas('success', 'El comprobante fue programado para envio al correo fiscal del pedido.');

        Queue::assertPushed(
            SendFiscalDocumentEmail::class,
            fn (SendFiscalDocumentEmail $job): bool => $job->documentId === $document->id,
        );
    }

    public function test_admin_cannot_queue_a_missing_or_annulled_document_for_delivery(): void
    {
        Queue::fake();
        $order = Order::factory()->paid()->create();
        $missingPdf = FiscalDocument::factory()->for($order)->create([
            'pdf_path' => 'fiscal-documents/missing.pdf',
        ]);

        $this->post(route('admin.orders.fiscal-documents.send', [$order->code, $missingPdf]))
            ->assertRedirect()
            ->assertSessionHas('error', 'El PDF vigente del comprobante ya no esta disponible.');

        $annulled = FiscalDocument::factory()->for($order)->create([
            'sale_document_slot' => null,
            'pdf_path' => 'fiscal-documents/annulled.pdf',
        ]);
        Storage::disk('local')->put($annulled->pdf_path, 'PDF de prueba');
        app(FiscalDocumentService::class)->annul($annulled, 'Anulado externamente', $this->adminUser);

        $this->post(route('admin.orders.fiscal-documents.send', [$order->code, $annulled]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Solo se puede enviar un comprobante fiscal emitido.');

        Queue::assertNothingPushed();
    }
}
