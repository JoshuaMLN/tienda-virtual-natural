<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FiscalDocumentCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'before_values',
        'after_values',
        'reason',
        'corrected_by',
        'corrected_by_name',
        'corrected_by_email',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('El historial de correcciones fiscales no se puede modificar.');
        });

        static::deleting(function (): never {
            throw new LogicException('El historial de correcciones fiscales no se puede eliminar.');
        });
    }

    protected function casts(): array
    {
        return ['before_values' => 'array', 'after_values' => 'array'];
    }

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
