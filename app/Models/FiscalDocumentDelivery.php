<?php

namespace App\Models;

use App\Enums\FiscalDeliveryStatus;
use App\Models\Concerns\Immutable;
use Database\Factories\FiscalDocumentDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalDocumentDelivery extends Model
{
    /** @use HasFactory<FiscalDocumentDeliveryFactory> */
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'fiscal_document_id',
        'recipient_email',
        'status',
        'attempted_by',
        'attempted_by_name',
        'attempted_by_email',
        'attempted_at',
        'sent_at',
        'error_message',
    ];

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }

    public function attemptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attempted_by');
    }

    protected function casts(): array
    {
        return [
            'status' => FiscalDeliveryStatus::class,
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
