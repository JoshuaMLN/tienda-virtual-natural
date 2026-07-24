<?php

namespace App\Models;

use App\Enums\OrderNotificationStatus;
use App\Enums\OrderNotificationType;
use Database\Factories\OrderNotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class OrderNotificationDelivery extends Model
{
    /** @use HasFactory<OrderNotificationDeliveryFactory> */
    use HasFactory;

    /** @var list<string> */
    private array $allowedMutationColumns = [];

    protected $fillable = [
        'order_id',
        'type',
        'recipient_email',
        'recipient_name',
        'status',
        'attempts',
        'queued_at',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'last_error',
    ];

    protected static function booted(): void
    {
        static::updating(function (OrderNotificationDelivery $delivery): void {
            $unexpected = array_diff(array_keys($delivery->getDirty()), $delivery->allowedMutationColumns);

            if ($unexpected !== []) {
                throw new LogicException('La identidad de una entrega de correo no se puede modificar.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('El historial de entregas de correo no se puede eliminar.');
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function recipientEmail(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * @internal Used by the transactional notification delivery service.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyDeliveryMutation(array $attributes): void
    {
        $allowed = [
            'status',
            'attempts',
            'last_attempt_at',
            'sent_at',
            'failed_at',
            'last_error',
        ];
        $unexpected = array_diff(array_keys($attributes), $allowed);

        if ($unexpected !== []) {
            throw new LogicException('La mutacion contiene campos no permitidos para la entrega de correo.');
        }

        $this->allowedMutationColumns = $allowed;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->allowedMutationColumns = [];
        }
    }

    protected function casts(): array
    {
        return [
            'type' => OrderNotificationType::class,
            'status' => OrderNotificationStatus::class,
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
