<?php

namespace App\Support\Legal;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Support\Facades\DB;

class LegalDocumentService
{
    public function __construct(
        private readonly StorefrontSettings $settings,
        private readonly LegalDocumentTemplate $template,
    ) {}

    /** @return array{document: LegalDocument, created: bool} */
    public function findOrCreateDraft(LegalDocumentType $type, User $creator): array
    {
        return DB::transaction(function () use ($type, $creator): array {
            $existing = LegalDocument::query()
                ->ofType($type)
                ->where('status', LegalDocumentStatus::Draft->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return ['document' => $existing, 'created' => false];
            }

            $snapshot = $this->settings->legalSnapshot();
            $content = $this->template->render($type, $snapshot);
            $document = new LegalDocument([
                'type' => $type,
                'title' => $content['title'],
                'body' => $content['body'],
                'created_by' => $creator->getKey(),
            ]);
            $document->forceFill([
                'draft_slot' => $type->value,
                'settings_snapshot' => $snapshot,
                'settings_fingerprint' => $this->settings->legalFingerprint($snapshot),
            ])->save();
            $document->refresh();

            return ['document' => $document, 'created' => true];
        });
    }

    public function updateDraft(LegalDocument $document, string $title, string $body): LegalDocument
    {
        if ($document->status !== LegalDocumentStatus::Draft) {
            throw new LegalDocumentException('Solo se pueden editar documentos en borrador.');
        }

        $document->update([
            'title' => trim($title),
            'body' => trim($body),
        ]);

        return $document->refresh();
    }

    public function refreshDraftTemplate(LegalDocument $document): LegalDocument
    {
        if ($document->status !== LegalDocumentStatus::Draft) {
            throw new LegalDocumentException('Solo se puede regenerar un documento en borrador.');
        }

        $snapshot = $this->settings->legalSnapshot();
        $content = $this->template->render($document->type, $snapshot);
        $document->applyTemplateRefresh(
            $content['title'],
            $content['body'],
            $snapshot,
            $this->settings->legalFingerprint($snapshot),
        );

        return $document->refresh();
    }

    public function publish(LegalDocument $document, User $publisher): LegalDocument
    {
        return DB::transaction(function () use ($document, $publisher): LegalDocument {
            LegalDocument::query()
                ->ofType($document->type)
                ->lockForUpdate()
                ->get();

            $draft = LegalDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            if ($draft->status !== LegalDocumentStatus::Draft) {
                throw new LegalDocumentException('Solo se puede publicar un borrador.');
            }

            if (trim($draft->title) === '' || trim($draft->body) === '') {
                throw new LegalDocumentException('El documento requiere titulo y contenido antes de publicarse.');
            }

            $snapshot = $this->settings->legalSnapshot();
            $fingerprint = $this->settings->legalFingerprint($snapshot);

            if (! hash_equals($fingerprint, (string) $draft->settings_fingerprint)) {
                throw new LegalDocumentException('La configuracion legal cambio despues de crear el borrador. Regeneralo y revisalo antes de publicar.');
            }

            $active = LegalDocument::query()
                ->ofType($draft->type)
                ->published()
                ->lockForUpdate()
                ->first();

            $nextVersion = ((int) LegalDocument::query()
                ->ofType($draft->type)
                ->whereNotNull('version')
                ->max('version')) + 1;

            $active?->applyReplacement();

            $draft->applyPublication(
                $nextVersion,
                $snapshot,
                $fingerprint,
                $publisher,
            );

            return $draft->refresh();
        });
    }

    public function discardDraft(LegalDocument $document): void
    {
        if ($document->status !== LegalDocumentStatus::Draft) {
            throw new LegalDocumentException('Solo se puede descartar un borrador.');
        }

        $document->delete();
    }

    public function active(LegalDocumentType $type): ?LegalDocument
    {
        return LegalDocument::query()
            ->ofType($type)
            ->published()
            ->where('active_slot', $type->value)
            ->first();
    }
}
