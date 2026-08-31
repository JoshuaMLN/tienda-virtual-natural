<?php

namespace App\Models;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxAffectation;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var list<string> */
    private array $allowedMutationColumns = [];

    protected $fillable = [
        'user_id',
        'pending_payment_owner_id',
        'customer_address_id',
        'checkout_idempotency_key',
        'checkout_review_reference',
        'customer_name',
        'customer_email',
        'customer_phone',
        'order_status',
        'payment_status',
        'delivery_status',
        'delivery_tracking_status',
        'delivery_current_cycle',
        'delivery_attempts_per_cycle',
        'delivery_max_automatic_cycles',
        'reshipment_payment_days',
        'pickup_hold_days',
        'delivery_method',
        'delivery_recipient_name',
        'delivery_phone',
        'delivery_department',
        'delivery_province',
        'delivery_district',
        'delivery_ubigeo',
        'delivery_address',
        'delivery_reference',
        'pickup_address',
        'fiscal_document_type',
        'fiscal_identity_document_type',
        'fiscal_identity_document_number',
        'fiscal_first_names',
        'fiscal_last_names',
        'fiscal_business_name',
        'fiscal_address',
        'fiscal_email',
        'terms_document_id',
        'terms_document_version',
        'terms_content_fingerprint',
        'terms_accepted_at',
        'terms_snapshot',
        'products_subtotal_cents',
        'discount_cents',
        'shipping_fee_cents',
        'shipping_tax_affectation',
        'shipping_tax_rate_bps',
        'shipping_net_value_cents',
        'shipping_tax_cents',
        'taxable_value_cents',
        'exempt_value_cents',
        'unaffected_value_cents',
        'net_value_cents',
        'tax_cents',
        'total_cents',
        'delivery_business_days_min',
        'delivery_business_days_max',
        'delivery_window_starts_at',
        'delivery_estimated_from',
        'delivery_estimated_to',
        'reshipment_payment_due_at',
        'pickup_ready_at',
        'pickup_deadline_at',
        'delivery_manual_follow_up_at',
        'delivery_tracking_completed_at',
        'reservation_expires_at',
        'paid_at',
        'cancelled_at',
        'expired_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            $unexpected = array_diff(array_keys($order->getDirty()), $order->allowedMutationColumns);

            if ($unexpected !== []) {
                throw new LogicException('Los snapshots e importes del pedido no se pueden modificar.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Los pedidos historicos no se pueden eliminar.');
        });
    }

    /**
     * @internal Used by the transactional state service.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyStateMutation(array $attributes): void
    {
        $this->applyLifecycleMutation($attributes, [
            'order_status',
            'payment_status',
            'delivery_status',
            'delivery_window_starts_at',
            'delivery_estimated_from',
            'delivery_estimated_to',
            'paid_at',
            'cancelled_at',
            'expired_at',
            'completed_at',
            'pending_payment_owner_id',
        ]);
    }

    /** @internal Used when a pending-payment lifecycle is closed. */
    public function releasePendingPaymentSlot(): void
    {
        if ($this->pending_payment_owner_id === null) {
            return;
        }

        $this->applyLifecycleMutation(
            ['pending_payment_owner_id' => null],
            ['pending_payment_owner_id'],
        );
    }

    /** @internal Used by the transactional reservation service. */
    public function applyReservationExpiration(?\DateTimeInterface $expiresAt): void
    {
        $this->applyLifecycleMutation(
            ['reservation_expires_at' => $expiresAt],
            ['reservation_expires_at'],
        );
    }

    /**
     * @internal Used by the fulfillment tracking service.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyFulfillmentMutation(array $attributes): void
    {
        $this->applyLifecycleMutation($attributes, [
            'delivery_tracking_status',
            'delivery_current_cycle',
            'reshipment_payment_due_at',
            'pickup_ready_at',
            'pickup_deadline_at',
            'delivery_manual_follow_up_at',
            'delivery_tracking_completed_at',
        ]);
    }

    /** @internal Used by the checkout cart cleanup coordinator. */
    public function markCartCleaned(?\DateTimeInterface $cleanedAt = null): void
    {
        $this->applyLifecycleMutation(
            ['cart_cleaned_at' => $cleanedAt ?? now()],
            ['cart_cleaned_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowedColumns
     */
    private function applyLifecycleMutation(array $attributes, array $allowedColumns): void
    {
        $unexpected = array_diff(array_keys($attributes), $allowedColumns);

        if ($unexpected !== []) {
            throw new LogicException('La mutacion contiene campos no permitidos para el ciclo de vida del pedido.');
        }

        $this->allowedMutationColumns = $allowedColumns;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->allowedMutationColumns = [];
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pendingPaymentOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_payment_owner_id');
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function termsDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'terms_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest('created_at')->oldest('id');
    }

    public function stockReservations(): HasManyThrough
    {
        return $this->hasManyThrough(
            StockReservation::class,
            OrderItem::class,
            'order_id',
            'order_item_id',
            'id',
            'id',
        );
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(OrderNotificationDelivery::class);
    }

    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)
            ->oldest('cycle')
            ->oldest('attempt_number');
    }

    public function saleDocument(): HasOne
    {
        return $this->hasOne(FiscalDocument::class)->where('sale_document_slot', 'sale');
    }

    protected function casts(): array
    {
        return [
            'sequence_year' => 'integer',
            'sequence_number' => 'integer',
            'pending_payment_owner_id' => 'integer',
            'terms_document_version' => 'integer',
            'terms_accepted_at' => 'datetime',
            'terms_snapshot' => 'array',
            'cart_cleaned_at' => 'datetime',
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_status' => DeliveryStatus::class,
            'delivery_tracking_status' => DeliveryTrackingStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'fiscal_document_type' => FiscalDocumentType::class,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::class,
            'shipping_tax_affectation' => TaxAffectation::class,
            'products_subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'shipping_fee_cents' => 'integer',
            'shipping_tax_rate_bps' => 'integer',
            'shipping_net_value_cents' => 'integer',
            'shipping_tax_cents' => 'integer',
            'taxable_value_cents' => 'integer',
            'exempt_value_cents' => 'integer',
            'unaffected_value_cents' => 'integer',
            'net_value_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'delivery_business_days_min' => 'integer',
            'delivery_business_days_max' => 'integer',
            'delivery_current_cycle' => 'integer',
            'delivery_attempts_per_cycle' => 'integer',
            'delivery_max_automatic_cycles' => 'integer',
            'reshipment_payment_days' => 'integer',
            'pickup_hold_days' => 'integer',
            'delivery_window_starts_at' => 'datetime',
            'delivery_estimated_from' => 'immutable_date',
            'delivery_estimated_to' => 'immutable_date',
            'reshipment_payment_due_at' => 'datetime',
            'pickup_ready_at' => 'datetime',
            'pickup_deadline_at' => 'datetime',
            'delivery_manual_follow_up_at' => 'datetime',
            'delivery_tracking_completed_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
