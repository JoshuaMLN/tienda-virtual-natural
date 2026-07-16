<?php

namespace App\Enums;

enum FiscalDocumentType: string
{
    case Receipt = 'receipt';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Boleta',
            self::Invoice => 'Factura',
            self::CreditNote => 'Nota de credito',
            self::DebitNote => 'Nota de debito',
        };
    }

    public function isSaleDocument(): bool
    {
        return $this === self::Receipt || $this === self::Invoice;
    }
}
