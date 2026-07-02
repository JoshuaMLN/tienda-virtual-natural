<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'notes',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
