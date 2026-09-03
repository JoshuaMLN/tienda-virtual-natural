<?php

namespace Tests\Feature\Orders;

use App\Enums\FiscalDeliveryStatus;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\PaymentStatus;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\User;
use App\Support\Orders\Fiscal\FiscalDocumentException;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FiscalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_can_register_requested_sale_document_with_relations_and_registrar_snapshot(): void
    {
        $order = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_email' => 'compras@example.test',
        ]);
        $registrar = User::factory()->admin()->create([
            'name' => 'Administrador Fiscal',
            'email' => 'fiscal@example.test',
        ]);
        $issuedAt = CarbonImmutable::parse('2026-07-16 10:30:00');

        $document = $this->fiscal()->registerSaleDocument(
            $order,
            FiscalDocumentType::Receipt,
            ' b001 ',
            ' 00000125 ',
            $issuedAt,
            ' fiscal/boletas/B001-00000125.pdf ',
            ' fiscal/boletas/B001-00000125.xml ',
            $registrar,
        );

        $this->assertSame(FiscalDocumentType::Receipt, $document->type);
        $this->assertSame(FiscalDocumentStatus::Issued, $document->status);
        $this->assertSame('sale', $document->sale_document_slot);
        $this->assertSame('B001', $document->series);
        $this->assertSame('00000125', $document->correlative);
        $this->assertSame('fiscal/boletas/B001-00000125.pdf', $document->pdf_path);
        $this->assertSame('fiscal/boletas/B001-00000125.xml', $document->xml_path);
        $this->assertTrue($document->issued_at->equalTo($issuedAt));
        $this->assertTrue($document->order->is($order));
        $this->assertTrue($document->registrar->is($registrar));
        $this->assertTrue($order->fresh()->saleDocument->is($document));
        $this->assertTrue($order->fiscalDocuments->contains($document));
        $this->assertTrue($registrar->registeredFiscalDocuments->contains($document));
        $this->assertSame('Administrador Fiscal', $document->registrar_name);
        $this->assertSame('fiscal@example.test', $document->registrar_email);

        $registrar->update([
            'name' => 'Administrador Modificado',
            'email' => 'fiscal.nuevo@example.test',
        ]);
        $registrar->delete();

        $document->refresh();

        $this->assertNull($document->registered_by);
        $this->assertNull($document->registrar);
        $this->assertSame('Administrador Fiscal', $document->registrar_name);
        $this->assertSame('fiscal@example.test', $document->registrar_email);
    }

    public function test_sale_document_requires_paid_order_requested_type_and_sale_document_kind(): void
    {
        $unpaid = Order::factory()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);

        $this->assertFiscalException(
            fn () => $this->fiscal()->registerSaleDocument(
                $unpaid,
                FiscalDocumentType::Receipt,
                'B001',
                '00000001',
                now(),
                'fiscal/receipt.pdf',
            ),
            'pedido pagado',
        );

        $paid = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);

        $this->assertFiscalException(
            fn () => $this->fiscal()->registerSaleDocument(
                $paid,
                FiscalDocumentType::Invoice,
                'F001',
                '00000001',
                now(),
                'fiscal/invoice.pdf',
            ),
            'no coincide',
        );

        $this->assertFiscalException(
            fn () => $this->fiscal()->registerSaleDocument(
                $paid,
                FiscalDocumentType::CreditNote,
                'BC01',
                '00000001',
                now(),
                'fiscal/note.pdf',
            ),
            'boleta o factura',
        );

        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertSame(PaymentStatus::Pending, $unpaid->refresh()->payment_status);
    }

    public function test_fiscal_number_is_unique_for_type_series_and_correlative(): void
    {
        $firstOrder = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);
        $secondOrder = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);

        $this->fiscal()->registerSaleDocument(
            $firstOrder,
            FiscalDocumentType::Receipt,
            'B001',
            '00000420',
            now(),
            'fiscal/first.pdf',
        );

        $this->expectException(QueryException::class);

        $this->fiscal()->registerSaleDocument(
            $secondOrder,
            FiscalDocumentType::Receipt,
            'b001',
            '00000420',
            now(),
            'fiscal/second.pdf',
        );
    }

    public function test_database_allows_only_one_primary_sale_document_per_order(): void
    {
        $order = Order::factory()->paid()->create();

        FiscalDocument::factory()->for($order)->create([
            'type' => FiscalDocumentType::Receipt,
            'sale_document_slot' => 'sale',
            'series' => 'B001',
            'correlative' => '00000010',
        ]);

        $this->expectException(QueryException::class);

        FiscalDocument::factory()->for($order)->create([
            'type' => FiscalDocumentType::Invoice,
            'sale_document_slot' => 'sale',
            'series' => 'F001',
            'correlative' => '00000011',
        ]);
    }

    public function test_related_credit_and_debit_notes_keep_parent_and_order_relations(): void
    {
        $order = Order::factory()->paid()->create([
            'fiscal_document_type' => FiscalDocumentType::Receipt,
        ]);
        $parent = $this->fiscal()->registerSaleDocument(
            $order,
            FiscalDocumentType::Receipt,
            'B001',
            '00000015',
            now(),
            'fiscal/receipt.pdf',
        );

        $creditNote = $this->fiscal()->registerRelatedDocument(
            $parent,
            FiscalDocumentType::CreditNote,
            'bc01',
            '00000001',
            now(),
            'fiscal/credit-note.pdf',
        );
        $debitNote = $this->fiscal()->registerRelatedDocument(
            $parent,
            FiscalDocumentType::DebitNote,
            'bd01',
            '00000002',
            now(),
            'fiscal/debit-note.pdf',
        );

        $this->assertSame('BC01', $creditNote->series);
        $this->assertSame('BD01', $debitNote->series);
        $this->assertNull($creditNote->sale_document_slot);
        $this->assertNull($debitNote->sale_document_slot);
        $this->assertTrue($creditNote->parentDocument->is($parent));
        $this->assertTrue($debitNote->parentDocument->is($parent));
        $this->assertTrue($creditNote->order->is($order));
        $this->assertTrue($parent->relatedDocuments->contains($creditNote));
        $this->assertTrue($parent->relatedDocuments->contains($debitNote));

        $this->assertFiscalException(
            fn () => $this->fiscal()->registerRelatedDocument(
                $parent,
                FiscalDocumentType::Receipt,
                'B002',
                '00000001',
                now(),
                'fiscal/invalid.pdf',
            ),
            'nota de credito o debito',
        );

        $this->assertFiscalException(
            fn () => $this->fiscal()->registerRelatedDocument(
                $creditNote,
                FiscalDocumentType::CreditNote,
                'BC02',
                '00000003',
                now(),
                'fiscal/nested-note.pdf',
            ),
            'directamente con una boleta o factura',
        );
    }

    public function test_fiscal_status_is_guarded_and_annulment_is_auditable_and_idempotent(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create();

        $this->assertLogicException(function () use ($document): void {
            $document->status = FiscalDocumentStatus::Annulled;
            $document->save();
        });
        $this->assertSame(FiscalDocumentStatus::Issued, $document->fresh()->status);
        $this->assertLogicException(fn () => $document->fresh()->update(['series' => 'B999']));

        $actor = User::factory()->admin()->create([
            'name' => 'Ana Administradora',
        ]);
        $annulled = $this->fiscal()->annul(
            $document->fresh(),
            '  Error en los datos fiscales  ',
            $actor,
        );

        $this->assertSame(FiscalDocumentStatus::Annulled, $annulled->status);
        $this->assertNotNull($annulled->annulled_at);
        $this->assertSame($actor->id, $annulled->annulled_by);
        $this->assertSame('Ana Administradora', $annulled->annulled_by_name);
        $this->assertSame($actor->email, $annulled->annulled_by_email);
        $this->assertSame('Error en los datos fiscales', $annulled->annulment_reason);

        $annulledAgain = $this->fiscal()->annul($annulled, 'Motivo distinto', $actor);

        $this->assertSame($annulled->id, $annulledAgain->id);
        $this->assertSame('Error en los datos fiscales', $annulledAgain->annulment_reason);

        $actor->update(['name' => 'Nombre nuevo']);
        $actor->delete();

        $annulledAgain->refresh();
        $this->assertNull($annulledAgain->annulled_by);
        $this->assertSame('Ana Administradora', $annulledAgain->annulled_by_name);
        $this->assertSame($actor->email, $annulledAgain->annulled_by_email);

        $this->assertLogicException(fn () => $annulledAgain->delete());
        $this->assertFiscalException(
            fn () => $this->fiscal()->recordDeliveryAttempt($annulledAgain, FiscalDeliveryStatus::Sent),
            'comprobante fiscal emitido',
        );
        $this->assertDatabaseHas('fiscal_documents', [
            'id' => $annulledAgain->id,
            'status' => FiscalDocumentStatus::Annulled->value,
            'annulment_reason' => 'Error en los datos fiscales',
        ]);
    }

    public function test_annulment_requires_a_non_empty_reason(): void
    {
        $document = FiscalDocument::factory()->create();

        $this->assertFiscalException(
            fn () => $this->fiscal()->annul($document, '   '),
            'requiere un motivo',
        );

        $this->assertSame(FiscalDocumentStatus::Issued, $document->refresh()->status);
        $this->assertNull($document->annulled_at);
    }

    public function test_combined_file_and_registration_correction_keeps_both_audit_histories(): void
    {
        $document = FiscalDocument::factory()->create([
            'series' => 'B001',
            'correlative' => '00000011',
            'issued_at' => CarbonImmutable::parse('2026-08-01'),
            'pdf_path' => 'fiscal/original.pdf',
        ]);
        $admin = User::factory()->admin()->create();

        $this->fiscal()->correct($document, 'fiscal/correct.pdf', [
            'series' => 'B001',
            'correlative' => '00000012',
            'issued_at' => CarbonImmutable::parse('2026-08-02'),
        ], 'PDF y correlativo transcritos incorrectamente', $admin);

        $document->refresh();
        $this->assertSame('fiscal/correct.pdf', $document->pdf_path);
        $this->assertSame('00000012', $document->correlative);
        $this->assertSame('2026-08-02', $document->issued_at->toDateString());
        $this->assertDatabaseHas('fiscal_document_file_versions', ['fiscal_document_id' => $document->id, 'version' => 1, 'pdf_path' => 'fiscal/original.pdf']);
        $this->assertDatabaseHas('fiscal_document_corrections', ['fiscal_document_id' => $document->id, 'reason' => 'PDF y correlativo transcritos incorrectamente']);

        $fileVersion = $document->fileVersions()->sole();
        $correction = $document->corrections()->sole();

        $this->assertLogicException(fn () => $fileVersion->update(['reason' => 'No permitido']));
        $this->assertLogicException(fn () => $fileVersion->delete());
        $this->assertLogicException(fn () => $correction->update(['reason' => 'No permitido']));
        $this->assertLogicException(fn () => $correction->delete());
    }

    public function test_annulled_sale_document_accepts_one_replacement_and_keeps_a_linear_chain(): void
    {
        $order = Order::factory()->paid()->create();
        $original = $this->fiscal()->registerSaleDocument($order, FiscalDocumentType::Receipt, 'B001', '00000031', now(), 'fiscal/original.pdf');
        $this->fiscal()->annul($original, 'Anulado externamente');

        $replacement = $this->fiscal()->registerReplacement($original, 'B001', '00000032', now(), 'fiscal/replacement.pdf');

        $this->assertSame(FiscalDocumentType::Receipt, $replacement->type);
        $this->assertTrue($replacement->parentDocument->is($original));
        $this->assertNull($replacement->sale_document_slot);
        $this->assertFiscalException(
            fn () => $this->fiscal()->registerReplacement($original, 'B001', '00000033', now(), 'fiscal/second.pdf'),
            'reemplazo vigente',
        );
    }

    public function test_delivery_attempts_keep_recipient_and_actor_snapshots_and_are_immutable(): void
    {
        $order = Order::factory()->paid()->create([
            'fiscal_email' => 'cliente.fiscal@example.test',
        ]);
        $document = FiscalDocument::factory()->for($order)->create();
        $actor = User::factory()->admin()->create([
            'name' => 'Operador de documentos',
            'email' => 'operador@example.test',
        ]);
        $sentAt = CarbonImmutable::parse('2026-07-16 14:00:00');

        $sent = $this->fiscal()->recordDeliveryAttempt(
            $document,
            FiscalDeliveryStatus::Sent,
            $actor,
            attemptedAt: $sentAt,
        );

        $this->assertFiscalException(
            fn () => $this->fiscal()->recordDeliveryAttempt(
                $document,
                FiscalDeliveryStatus::Failed,
                $actor,
            ),
            'requiere el detalle del error',
        );

        $failed = $this->fiscal()->recordDeliveryAttempt(
            $document,
            FiscalDeliveryStatus::Failed,
            $actor,
            '  Brevo rechazo temporalmente el mensaje  ',
            $sentAt->addMinute(),
        );

        $this->assertTrue($sent->fiscalDocument->is($document));
        $this->assertTrue($sent->attemptedBy->is($actor));
        $this->assertSame('cliente.fiscal@example.test', $sent->recipient_email);
        $this->assertSame('Operador de documentos', $sent->attempted_by_name);
        $this->assertSame('operador@example.test', $sent->attempted_by_email);
        $this->assertTrue($sent->sent_at->equalTo($sentAt));
        $this->assertNull($sent->error_message);
        $this->assertSame(FiscalDeliveryStatus::Failed, $failed->status);
        $this->assertNull($failed->sent_at);
        $this->assertSame('Brevo rechazo temporalmente el mensaje', $failed->error_message);
        $this->assertSame([$sent->id, $failed->id], $document->deliveries->pluck('id')->all());
        $this->assertTrue($actor->fiscalDocumentDeliveries->contains($sent));

        $actor->update([
            'name' => 'Operador Renombrado',
            'email' => 'otro.operador@example.test',
        ]);
        $actor->delete();

        $sent->refresh();
        $failed->refresh();

        $this->assertNull($sent->attempted_by);
        $this->assertNull($failed->attempted_by);
        $this->assertSame('cliente.fiscal@example.test', $sent->recipient_email);
        $this->assertSame('cliente.fiscal@example.test', $failed->recipient_email);
        $this->assertSame('Operador de documentos', $sent->attempted_by_name);
        $this->assertSame('operador@example.test', $sent->attempted_by_email);
        $this->assertSame('Brevo rechazo temporalmente el mensaje', $failed->error_message);

        $this->assertLogicException(fn () => $sent->update(['recipient_email' => 'alterado@example.test']));
        $this->assertLogicException(fn () => $failed->fresh()->delete());

        $this->assertDatabaseCount('fiscal_document_deliveries', 2);
    }

    private function fiscal(): FiscalDocumentService
    {
        return app(FiscalDocumentService::class);
    }

    /** @param callable(): mixed $callback */
    private function assertFiscalException(callable $callback, string $messageFragment): void
    {
        try {
            $callback();
            $this->fail('Se esperaba una regla fiscal invalida.');
        } catch (FiscalDocumentException $exception) {
            $this->assertStringContainsString($messageFragment, $exception->getMessage());
        }
    }

    /** @param callable(): mixed $callback */
    private function assertLogicException(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Se esperaba que la operacion estuviera protegida por el dominio.');
        } catch (LogicException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
    }
}
