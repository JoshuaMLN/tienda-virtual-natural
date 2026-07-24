<?php

namespace App\Support\Orders;

use App\Enums\FiscalDocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\FiscalDocument;
use App\Models\Order;
use Illuminate\Support\Str;

class CustomerFiscalDocumentPresenter
{
    public function __construct(
        private readonly CustomerOrderDateFormatter $dates,
    ) {}

    /** @return array<string, mixed> */
    public function present(Order $order): array
    {
        $documents = $order->fiscalDocuments
            ->map(fn (FiscalDocument $document): array => [
                'type' => $document->type->label(),
                'reference' => strtoupper($document->series).'-'.$document->correlative,
                'issued_at' => $this->dates->descriptive($document->issued_at),
                'status' => $document->status->label(),
                'is_annulled' => $document->status === FiscalDocumentStatus::Annulled,
                'download_url' => route('account.orders.fiscal-documents.download', [
                    'code' => $order->code,
                    'document' => $document->getKey(),
                ]),
            ])
            ->all();

        $paymentConfirmed = $order->payment_status === PaymentStatus::Paid;

        return [
            'visible' => $paymentConfirmed || $documents !== [],
            'pending_issue' => $paymentConfirmed && $documents === [],
            'items' => $documents,
        ];
    }

    public function downloadName(FiscalDocument $document): string
    {
        $type = Str::slug($document->type->label());
        $series = strtoupper(Str::slug($document->series));
        $correlative = strtoupper(Str::slug($document->correlative));
        $reference = implode('-', array_filter([$type, $series, $correlative]));

        return ($reference !== '' ? $reference : 'comprobante-'.$document->getKey()).'.pdf';
    }
}
