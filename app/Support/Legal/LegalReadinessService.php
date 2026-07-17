<?php

namespace App\Support\Legal;

use App\Enums\LegalDocumentType;
use App\Support\Settings\StorefrontSettings;

class LegalReadinessService
{
    public function __construct(
        private readonly StorefrontSettings $settings,
        private readonly LegalDocumentService $documents,
    ) {}

    /** @return list<string> */
    public function missingRequirements(): array
    {
        $missing = $this->settings->missingLegalProfileFields();
        $fingerprint = $this->settings->legalFingerprint();

        foreach (LegalDocumentType::cases() as $type) {
            $document = $this->documents->active($type);

            if ($document === null) {
                $missing[] = "Publicar {$type->label()}";
            } elseif (! hash_equals($fingerprint, (string) $document->settings_fingerprint)) {
                $missing[] = "Republicar {$type->label()} con la configuracion vigente";
            }
        }

        return $missing;
    }

    public function canEnableLiveSales(): bool
    {
        return $this->missingRequirements() === [];
    }

    public function isDemoMode(): bool
    {
        return ! $this->settings->liveSalesRequested() || ! $this->canEnableLiveSales();
    }
}
