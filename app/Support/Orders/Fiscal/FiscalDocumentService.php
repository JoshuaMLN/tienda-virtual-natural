<?php

namespace App\Support\Orders\Fiscal;

use App\Enums\FiscalDeliveryStatus;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\PaymentStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentDelivery;
use App\Models\Order;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class FiscalDocumentService
{
    public function registerSaleDocument(
        Order $order,
        FiscalDocumentType $type,
        string $series,
        string $correlative,
        DateTimeInterface $issuedAt,
        string $pdfPath,
        ?string $xmlPath = null,
        ?User $registrar = null,
    ): FiscalDocument {
        return DB::transaction(function () use ($order, $type, $series, $correlative, $issuedAt, $pdfPath, $xmlPath, $registrar): FiscalDocument {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status !== PaymentStatus::Paid) {
                throw new FiscalDocumentException('Solo un pedido pagado puede recibir un comprobante fiscal.');
            }

            if (! $type->isSaleDocument()) {
                throw new FiscalDocumentException('El documento principal debe ser una boleta o factura.');
            }

            if ($locked->fiscal_document_type !== $type) {
                throw new FiscalDocumentException('El tipo de comprobante no coincide con el solicitado por el cliente.');
            }

            if ($locked->saleDocument()->exists()) {
                throw new FiscalDocumentException('El pedido ya tiene un comprobante principal registrado.');
            }

            return $locked->fiscalDocuments()->create($this->documentAttributes(
                type: $type,
                series: $series,
                correlative: $correlative,
                issuedAt: $issuedAt,
                pdfPath: $pdfPath,
                xmlPath: $xmlPath,
                registrar: $registrar,
                saleDocumentSlot: 'sale',
            ));
        });
    }

    public function registerRelatedDocument(
        FiscalDocument $parent,
        FiscalDocumentType $type,
        string $series,
        string $correlative,
        DateTimeInterface $issuedAt,
        string $pdfPath,
        ?string $xmlPath = null,
        ?User $registrar = null,
    ): FiscalDocument {
        if (! in_array($type, [FiscalDocumentType::CreditNote, FiscalDocumentType::DebitNote], true)) {
            throw new FiscalDocumentException('Un documento relacionado debe ser una nota de credito o debito.');
        }

        return DB::transaction(function () use ($parent, $type, $series, $correlative, $issuedAt, $pdfPath, $xmlPath, $registrar): FiscalDocument {
            $locked = FiscalDocument::query()
                ->with('order')
                ->whereKey($parent->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->type->isSaleDocument()) {
                throw new FiscalDocumentException('Las notas deben relacionarse directamente con una boleta o factura.');
            }

            if ($locked->status === FiscalDocumentStatus::Annulled) {
                throw new FiscalDocumentException('No se puede registrar una nota sobre un comprobante anulado.');
            }

            if ($locked->order->payment_status !== PaymentStatus::Paid) {
                throw new FiscalDocumentException('El pedido del comprobante debe permanecer pagado.');
            }

            return FiscalDocument::query()->create(array_merge(
                $this->documentAttributes($type, $series, $correlative, $issuedAt, $pdfPath, $xmlPath, $registrar),
                [
                    'order_id' => $locked->order_id,
                    'parent_document_id' => $locked->getKey(),
                ],
            ));
        });
    }

    public function annul(FiscalDocument $document, string $reason, ?User $actor = null): FiscalDocument
    {
        return DB::transaction(function () use ($document, $reason, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === FiscalDocumentStatus::Annulled) {
                return $locked;
            }

            if (trim($reason) === '') {
                throw new FiscalDocumentException('La anulacion requiere un motivo.');
            }

            $locked->applyStatusMutation([
                'status' => FiscalDocumentStatus::Annulled,
                'annulled_at' => now(),
                'annulled_by' => $actor?->getKey(),
                'annulled_by_name' => $actor?->name,
                'annulled_by_email' => $actor?->email,
                'annulment_reason' => trim($reason),
            ]);

            return $locked->refresh();
        });
    }

    public function registerReplacement(FiscalDocument $parent, string $series, string $correlative, DateTimeInterface $issuedAt, string $pdfPath, ?User $registrar = null): FiscalDocument
    {
        return DB::transaction(function () use ($parent, $series, $correlative, $issuedAt, $pdfPath, $registrar): FiscalDocument {
            $locked = FiscalDocument::query()->with('order')->whereKey($parent->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->type->isSaleDocument() || $locked->status !== FiscalDocumentStatus::Annulled) {
                throw new FiscalDocumentException('Solo un comprobante principal anulado puede recibir reemplazo.');
            }
            if ($locked->relatedDocuments()
                ->where('type', $locked->type)
                ->whereNull('sale_document_slot')
                ->where('status', FiscalDocumentStatus::Issued)
                ->exists()) {
                throw new FiscalDocumentException('El comprobante ya tiene un reemplazo vigente.');
            }

            return FiscalDocument::query()->create(array_merge(
                $this->documentAttributes($locked->type, $series, $correlative, $issuedAt, $pdfPath, null, $registrar),
                ['order_id' => $locked->order_id, 'parent_document_id' => $locked->getKey()],
            ));
        });
    }

    public function replacePdf(FiscalDocument $document, string $pdfPath, string $reason, ?User $actor = null): FiscalDocument
    {
        return $this->correct($document, $pdfPath, null, $reason, $actor);
    }

    /** @param null|array{series:string,correlative:string,issued_at:DateTimeInterface} $values */
    public function correct(FiscalDocument $document, ?string $pdfPath, ?array $values, string $reason, ?User $actor = null): FiscalDocument
    {
        return DB::transaction(function () use ($document, $pdfPath, $values, $reason, $actor): FiscalDocument {
            $locked = FiscalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $reason = trim($reason);
            $pdfPath = trim((string) $pdfPath);

            if ($locked->status !== FiscalDocumentStatus::Issued) {
                throw new FiscalDocumentException('Solo se puede corregir un comprobante fiscal emitido.');
            }

            if ($reason === '') {
                throw new FiscalDocumentException('La correccion requiere un motivo.');
            }

            $mutation = [];
            if ($pdfPath !== '') {
                $locked->fileVersions()->create([
                    'version' => ((int) $locked->fileVersions()->max('version')) + 1,
                    'pdf_path' => $locked->pdf_path,
                    'reason' => $reason,
                    'replaced_by' => $actor?->getKey(),
                    'replaced_by_name' => $actor?->name,
                    'replaced_by_email' => $actor?->email,
                ]);
                $mutation['pdf_path'] = $pdfPath;
            }

            if ($values !== null) {
                $after = [
                    'series' => strtoupper(trim($values['series'])),
                    'correlative' => trim($values['correlative']),
                    'issued_at' => $values['issued_at'],
                ];

                if ($after['series'] === '' || $after['correlative'] === '') {
                    throw new FiscalDocumentException('La correccion del registro requiere valores completos.');
                }

                $before = ['series' => $locked->series, 'correlative' => $locked->correlative, 'issued_at' => $locked->issued_at->toDateString()];
                $auditAfter = ['series' => $after['series'], 'correlative' => $after['correlative'], 'issued_at' => $after['issued_at']->format('Y-m-d')];

                if ($before !== $auditAfter) {
                    $locked->corrections()->create([
                        'before_values' => $before,
                        'after_values' => $auditAfter,
                        'reason' => $reason,
                        'corrected_by' => $actor?->getKey(),
                        'corrected_by_name' => $actor?->name,
                        'corrected_by_email' => $actor?->email,
                    ]);
                    $mutation = array_merge($mutation, $after);
                }
            }

            if ($mutation === []) {
                throw new FiscalDocumentException('La correccion no contiene cambios.');
            }

            $locked->applyStatusMutation($mutation);

            return $locked->refresh();
        });
    }

    /** @param array{series:string,correlative:string,issued_at:DateTimeInterface} $values */
    public function correctRegistration(FiscalDocument $document, array $values, string $reason, ?User $actor = null): FiscalDocument
    {
        return $this->correct($document, null, $values, $reason, $actor);
    }

    public function recordDeliveryAttempt(
        FiscalDocument $document,
        FiscalDeliveryStatus $status,
        ?User $actor = null,
        ?string $errorMessage = null,
        ?DateTimeInterface $attemptedAt = null,
        ?array $actorSnapshot = null,
    ): FiscalDocumentDelivery {
        $document->loadMissing('order');

        $this->assertCanBeDelivered($document);

        if ($status === FiscalDeliveryStatus::Failed && trim((string) $errorMessage) === '') {
            throw new FiscalDocumentException('Un envio fallido requiere el detalle del error.');
        }

        $moment = $attemptedAt ?? now();

        return $document->deliveries()->create([
            'recipient_email' => $document->order->fiscal_email,
            'status' => $status,
            'attempted_by' => $actorSnapshot['id'] ?? $actor?->getKey(),
            'attempted_by_name' => $actorSnapshot['name'] ?? $actor?->name,
            'attempted_by_email' => $actorSnapshot['email'] ?? $actor?->email,
            'attempted_at' => $moment,
            'sent_at' => $status === FiscalDeliveryStatus::Sent ? $moment : null,
            'error_message' => $status === FiscalDeliveryStatus::Failed ? trim((string) $errorMessage) : null,
        ]);
    }

    public function assertCanBeDelivered(FiscalDocument $document): void
    {
        if ($document->status !== FiscalDocumentStatus::Issued) {
            throw new FiscalDocumentException('Solo se puede enviar un comprobante fiscal emitido.');
        }

        $pdfPath = trim((string) $document->pdf_path);

        if ($pdfPath === '' || strtolower(pathinfo($pdfPath, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new FiscalDocumentException('El comprobante no tiene un PDF vigente para enviar.');
        }
    }

    /** @return array<string, mixed> */
    private function documentAttributes(
        FiscalDocumentType $type,
        string $series,
        string $correlative,
        DateTimeInterface $issuedAt,
        string $pdfPath,
        ?string $xmlPath,
        ?User $registrar,
        ?string $saleDocumentSlot = null,
    ): array {
        $series = strtoupper(trim($series));
        $correlative = trim($correlative);
        $pdfPath = trim($pdfPath);

        if ($series === '' || $correlative === '' || $pdfPath === '') {
            throw new FiscalDocumentException('Serie, correlativo y PDF son obligatorios.');
        }

        return [
            'type' => $type,
            'sale_document_slot' => $saleDocumentSlot,
            'series' => $series,
            'correlative' => $correlative,
            'issued_at' => $issuedAt,
            'status' => FiscalDocumentStatus::Issued,
            'pdf_path' => $pdfPath,
            'xml_path' => $xmlPath ? trim($xmlPath) : null,
            'registered_by' => $registrar?->getKey(),
            'registrar_name' => $registrar?->name,
            'registrar_email' => $registrar?->email,
        ];
    }
}
