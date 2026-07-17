<?php

namespace App\Models;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use Database\Factories\LegalDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LegalDocument extends Model
{
    /** @use HasFactory<LegalDocumentFactory> */
    use HasFactory;

    /** @var list<string> */
    private array $allowedLifecycleColumns = [];

    protected $fillable = [
        'type',
        'title',
        'body',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::updating(function (LegalDocument $document): void {
            $dirty = array_keys($document->getDirty());
            $originalStatus = LegalDocumentStatus::from((string) $document->getRawOriginal('status'));

            if ($document->allowedLifecycleColumns !== []) {
                $unexpected = array_diff($dirty, $document->allowedLifecycleColumns);

                if ($unexpected !== []) {
                    throw new LogicException('La transicion legal contiene campos no permitidos.');
                }

                return;
            }

            if ($originalStatus !== LegalDocumentStatus::Draft) {
                throw new LogicException('Una version legal publicada no se puede modificar.');
            }

            $unexpected = array_diff($dirty, ['title', 'body']);

            if ($unexpected !== []) {
                throw new LogicException('El ciclo de vida del documento solo puede cambiar mediante el servicio legal.');
            }
        });

        static::deleting(function (LegalDocument $document): void {
            if ($document->status !== LegalDocumentStatus::Draft) {
                throw new LogicException('Una version legal publicada no se puede eliminar.');
            }
        });
    }

    public function applyPublication(
        int $version,
        array $settingsSnapshot,
        string $settingsFingerprint,
        ?User $publisher,
    ): void {
        $this->applyLifecycleMutation([
            'version' => $version,
            'status' => LegalDocumentStatus::Published,
            'active_slot' => $this->type->value,
            'draft_slot' => null,
            'settings_snapshot' => $settingsSnapshot,
            'settings_fingerprint' => $settingsFingerprint,
            'published_by' => $publisher?->getKey(),
            'published_at' => now(),
        ], [
            'version',
            'status',
            'active_slot',
            'draft_slot',
            'settings_snapshot',
            'settings_fingerprint',
            'published_by',
            'published_at',
        ]);
    }

    /** @param array<string, int|string> $settingsSnapshot */
    public function applyTemplateRefresh(
        string $title,
        string $body,
        array $settingsSnapshot,
        string $settingsFingerprint,
    ): void {
        if ($this->status !== LegalDocumentStatus::Draft) {
            throw new LogicException('Solo se puede regenerar un documento en borrador.');
        }

        $this->applyLifecycleMutation([
            'title' => $title,
            'body' => $body,
            'settings_snapshot' => $settingsSnapshot,
            'settings_fingerprint' => $settingsFingerprint,
        ], ['title', 'body', 'settings_snapshot', 'settings_fingerprint']);
    }

    public function applyReplacement(): void
    {
        $this->applyLifecycleMutation([
            'status' => LegalDocumentStatus::Replaced,
            'active_slot' => null,
            'replaced_at' => now(),
        ], ['status', 'active_slot', 'replaced_at']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowedColumns
     */
    private function applyLifecycleMutation(array $attributes, array $allowedColumns): void
    {
        $this->allowedLifecycleColumns = $allowedColumns;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->allowedLifecycleColumns = [];
        }
    }

    public function scopeOfType(Builder $query, LegalDocumentType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', LegalDocumentStatus::Published->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'status' => LegalDocumentStatus::class,
            'version' => 'integer',
            'settings_snapshot' => 'array',
            'published_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }
}
