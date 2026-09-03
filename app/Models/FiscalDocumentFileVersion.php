<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FiscalDocumentFileVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'version',
        'pdf_path',
        'reason',
        'replaced_by',
        'replaced_by_name',
        'replaced_by_email',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('El historial de versiones del comprobante fiscal no se puede modificar.');
        });

        static::deleting(function (): never {
            throw new LogicException('El historial de versiones del comprobante fiscal no se puede eliminar.');
        });
    }

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
