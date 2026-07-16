<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\StockReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class StockReservation extends Model
{
    /** @use HasFactory<StockReservationFactory> */
    use HasFactory;

    private bool $lifecycleMutationAllowed = false;

    protected $fillable = [
        'order_item_id',
        'reserve_inventory_movement_id',
        'release_inventory_movement_id',
        'quantity',
        'status',
        'expires_at',
        'consumed_at',
        'released_at',
        'expired_at',
        'release_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (StockReservation $reservation): void {
            if (! $reservation->lifecycleMutationAllowed) {
                throw new LogicException('Las reservas solo pueden cambiar mediante el servicio de reservas.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Las reservas historicas no se pueden eliminar.');
        });
    }

    /**
     * @internal Used by the transactional reservation service.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyStatusMutation(array $attributes): void
    {
        $allowed = [
            'status',
            'release_inventory_movement_id',
            'consumed_at',
            'released_at',
            'expired_at',
            'release_reason',
        ];
        $unexpected = array_diff(array_keys($attributes), $allowed);

        if ($unexpected !== []) {
            throw new LogicException('La mutacion contiene campos no permitidos para la reserva.');
        }

        $this->lifecycleMutationAllowed = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->lifecycleMutationAllowed = false;
        }
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reserveInventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'reserve_inventory_movement_id');
    }

    public function releaseInventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'release_inventory_movement_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }
}
