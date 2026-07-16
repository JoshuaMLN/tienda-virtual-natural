<?php

namespace App\Models;

use App\Enums\OrderHistoryDomain;
use App\Models\Concerns\Immutable;
use Database\Factories\OrderStatusHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    /** @use HasFactory<OrderStatusHistoryFactory> */
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'domain',
        'from_status',
        'to_status',
        'actor_id',
        'actor_name',
        'actor_email',
        'reason',
        'metadata',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'domain' => OrderHistoryDomain::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
