<?php

namespace App\Enums;

enum OrderNotificationStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Sending => 'Enviando',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
        };
    }
}
