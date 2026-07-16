<?php

namespace App\Models;

use App\Enums\TaxAffectation;
use App\Models\Concerns\Immutable;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory, Immutable;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_sku',
        'product_name',
        'product_image',
        'product_presentation',
        'sale_unit',
        'quantity',
        'tax_affectation',
        'tax_rate_bps',
        'unit_price_cents',
        'gross_total_cents',
        'discount_cents',
        'net_value_cents',
        'tax_cents',
        'total_cents',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockReservation(): HasOne
    {
        return $this->hasOne(StockReservation::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'tax_affectation' => TaxAffectation::class,
            'tax_rate_bps' => 'integer',
            'unit_price_cents' => 'integer',
            'gross_total_cents' => 'integer',
            'discount_cents' => 'integer',
            'net_value_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
