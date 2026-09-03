<?php

namespace App\Jobs;

use App\Enums\FiscalDeliveryStatus;
use App\Models\FiscalDocument;
use App\Notifications\FiscalDocumentNotification;
use App\Support\Orders\Fiscal\FiscalDocumentException;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendFiscalDocumentEmail implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use FoundationQueueable;

    public int $tries = 1;

    public int $timeout = 30;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $documentId,
        private readonly ?int $attemptedBy,
        private readonly ?string $attemptedByName,
        private readonly ?string $attemptedByEmail,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(FiscalDocumentService $documents): void
    {
        $document = FiscalDocument::query()
            ->with('order')
            ->find($this->documentId);

        if ($document === null) {
            return;
        }

        try {
            $documents->assertCanBeDelivered($document);

            if (! Storage::disk('local')->exists($document->pdf_path)) {
                throw new FiscalDocumentException('El PDF vigente del comprobante ya no esta disponible.');
            }
        } catch (FiscalDocumentException $exception) {
            $this->recordFailureIfStillIssued($documents, $document, $exception);

            return;
        }

        try {
            Notification::route('mail', $document->order->fiscal_email)
                ->notifyNow(new FiscalDocumentNotification($document));
        } catch (Throwable $exception) {
            report($exception);
            $this->recordFailureIfStillIssued($documents, $document, $exception);

            return;
        }

        $documents->recordDeliveryAttempt(
            $document,
            FiscalDeliveryStatus::Sent,
            actorSnapshot: $this->actorSnapshot(),
        );
    }

    private function recordFailureIfStillIssued(
        FiscalDocumentService $documents,
        FiscalDocument $document,
        Throwable $exception,
    ): void {
        try {
            $document->refresh()->loadMissing('order');
            $documents->recordDeliveryAttempt(
                $document,
                FiscalDeliveryStatus::Failed,
                errorMessage: mb_substr($exception->getMessage(), 0, 5_000),
                actorSnapshot: $this->actorSnapshot(),
            );
        } catch (FiscalDocumentException) {
            // El documento pudo anularse tras la solicitud: no se envia ni se altera su historial legal.
        }
    }

    /** @return array{id:?int,name:?string,email:?string} */
    private function actorSnapshot(): array
    {
        return [
            'id' => $this->attemptedBy,
            'name' => $this->attemptedByName,
            'email' => $this->attemptedByEmail,
        ];
    }
}
