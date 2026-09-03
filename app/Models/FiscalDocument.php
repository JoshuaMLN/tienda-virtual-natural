<?php

namespace App\Models;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use Database\Factories\FiscalDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class FiscalDocument extends Model
{
    /** @use HasFactory<FiscalDocumentFactory> */
    use HasFactory;

    private bool $statusMutationAllowed = false;

    protected $fillable = [
        'order_id',
        'parent_document_id',
        'type',
        'sale_document_slot',
        'series',
        'correlative',
        'issued_at',
        'status',
        'pdf_path',
        'xml_path',
        'registered_by',
        'registrar_name',
        'registrar_email',
        'annulled_at',
        'annulled_by',
        'annulled_by_name',
        'annulled_by_email',
        'annulment_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (FiscalDocument $document): void {
            if (! $document->statusMutationAllowed) {
                throw new LogicException('Los datos de un comprobante fiscal emitido no se pueden modificar.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Los comprobantes fiscales se anulan, no se eliminan.');
        });
    }

    /**
     * @internal Used by the fiscal document service.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function applyStatusMutation(array $attributes): void
    {
        $allowed = ['status', 'annulled_at', 'annulled_by', 'annulled_by_name', 'annulled_by_email', 'annulment_reason', 'pdf_path', 'series', 'correlative', 'issued_at'];
        $unexpected = array_diff(array_keys($attributes), $allowed);

        if ($unexpected !== []) {
            throw new LogicException('La mutacion contiene campos no permitidos para el comprobante fiscal.');
        }

        $this->statusMutationAllowed = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->statusMutationAllowed = false;
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    public function relatedDocuments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function annulledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulled_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(FiscalDocumentDelivery::class)->oldest('attempted_at')->oldest('id');
    }

    public function fileVersions(): HasMany
    {
        return $this->hasMany(FiscalDocumentFileVersion::class)->latest('version');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(FiscalDocumentCorrection::class)->latest('id');
    }

    protected function casts(): array
    {
        return [
            'type' => FiscalDocumentType::class,
            'status' => FiscalDocumentStatus::class,
            'issued_at' => 'datetime',
            'annulled_at' => 'datetime',
        ];
    }
}
