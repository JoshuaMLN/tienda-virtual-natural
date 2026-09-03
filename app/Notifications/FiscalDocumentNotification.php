<?php

namespace App\Notifications;

use App\Models\FiscalDocument;
use App\Support\Orders\CustomerFiscalDocumentPresenter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FiscalDocumentNotification extends Notification
{
    public function __construct(
        private readonly FiscalDocument $document,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = $this->document->series.'-'.$this->document->correlative;

        return (new MailMessage)
            ->subject("Tu comprobante {$reference} | ".config('app.name'))
            ->greeting('Hola,')
            ->line("Adjuntamos tu comprobante {$this->document->type->label()} {$reference} del pedido {$this->document->order->code}.")
            ->line('Conserva este archivo para tus registros.')
            ->attachFromStorageDisk(
                'local',
                $this->document->pdf_path,
                app(CustomerFiscalDocumentPresenter::class)->downloadName($this->document),
                ['mime' => 'application/pdf'],
            );
    }
}
