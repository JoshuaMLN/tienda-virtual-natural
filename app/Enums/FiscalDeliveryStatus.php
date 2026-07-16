<?php

namespace App\Enums;

enum FiscalDeliveryStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
        };
    }
}
