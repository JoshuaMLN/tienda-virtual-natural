<?php

namespace App\Models;

use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Models\Concerns\Immutable;
use Database\Factories\DeliveryAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAttempt extends Model
{
    /** @use HasFactory<DeliveryAttemptFactory> */
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'operation_token',
        'cycle',
        'attempt_number',
        'counted_attempt_number',
        'result',
        'attribution',
        'consumes_attempt',
        'responsible_name',
        'reason',
        'occurred_at',
        'recorded_by_id',
        'recorded_by_name',
        'recorded_by_email',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    protected function casts(): array
    {
        return [
            'cycle' => 'integer',
            'attempt_number' => 'integer',
            'counted_attempt_number' => 'integer',
            'result' => DeliveryAttemptResult::class,
            'attribution' => DeliveryAttemptAttribution::class,
            'consumes_attempt' => 'boolean',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'datetime',
        ];
    }
}
