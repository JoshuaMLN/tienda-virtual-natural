<?php

namespace Database\Factories;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalDocument>
 */
class LegalDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => LegalDocumentType::Terms,
            'version' => null,
            'title' => 'Borrador legal',
            'body' => '## Contenido\n\nDocumento legal de prueba.',
            'status' => LegalDocumentStatus::Draft,
            'active_slot' => null,
            'draft_slot' => LegalDocumentType::Terms->value,
            'settings_snapshot' => null,
            'settings_fingerprint' => null,
            'published_at' => null,
            'replaced_at' => null,
        ];
    }

    public function privacy(): static
    {
        return $this->state(fn (): array => [
            'type' => LegalDocumentType::Privacy,
            'draft_slot' => LegalDocumentType::Privacy->value,
        ]);
    }

    public function published(int $version = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'version' => $version,
            'status' => LegalDocumentStatus::Published,
            'active_slot' => $attributes['type'] instanceof LegalDocumentType
                ? $attributes['type']->value
                : $attributes['type'],
            'draft_slot' => null,
            'settings_snapshot' => [],
            'settings_fingerprint' => hash('sha256', '[]'),
            'published_at' => now(),
        ]);
    }
}
