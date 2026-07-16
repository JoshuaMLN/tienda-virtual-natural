<?php

namespace App\Enums;

enum FiscalDocumentStatus: string
{
    case Issued = 'issued';
    case Annulled = 'annulled';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Emitido',
            self::Annulled => 'Anulado',
        };
    }
}
