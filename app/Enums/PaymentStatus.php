<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case RefundPending = 'refund_pending';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Paid => 'Pagado',
            self::Failed => 'Fallido',
            self::Expired => 'Vencido',
            self::RefundPending => 'Reembolso pendiente',
            self::Refunded => 'Reembolsado',
        };
    }
}
