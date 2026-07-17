<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLegalDocumentDraftRequest;
use App\Http\Requests\Admin\UpdateLegalDocumentRequest;
use App\Models\LegalDocument;
use App\Support\Legal\LegalDocumentException;
use App\Support\Legal\LegalDocumentService;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function store(
        StoreLegalDocumentDraftRequest $request,
        LegalDocumentService $documents,
    ): RedirectResponse {
        $result = $documents->findOrCreateDraft(
            LegalDocumentType::from($request->validated('type')),
            $request->user(),
        );

        return redirect()
            ->route('admin.legal.documents.edit', $result['document'])
            ->with(
                $result['created'] ? 'success' : 'info',
                $result['created'] ? 'Borrador creado desde la configuracion vigente.' : 'Ya existia un borrador para este documento.',
            );
    }

    public function edit(
        LegalDocument $legalDocument,
        StorefrontSettings $settings,
    ): View {
        return view('admin.legal.edit', [
            'legalDocument' => $legalDocument->load(['creator', 'publisher']),
            'draftIsStale' => $legalDocument->status === LegalDocumentStatus::Draft
                && ! hash_equals($settings->legalFingerprint(), (string) $legalDocument->settings_fingerprint),
        ]);
    }

    public function update(
        UpdateLegalDocumentRequest $request,
        LegalDocument $legalDocument,
        LegalDocumentService $documents,
    ): RedirectResponse {
        try {
            $documents->updateDraft(
                $legalDocument,
                $request->validated('title'),
                $request->validated('body'),
            );
        } catch (LegalDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Borrador legal guardado.');
    }

    public function refreshTemplate(
        LegalDocument $legalDocument,
        LegalDocumentService $documents,
    ): RedirectResponse {
        try {
            $documents->refreshDraftTemplate($legalDocument);
        } catch (LegalDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Borrador regenerado con la configuracion legal vigente.');
    }

    public function publish(
        Request $request,
        LegalDocument $legalDocument,
        LegalDocumentService $documents,
    ): RedirectResponse {
        try {
            $published = $documents->publish($legalDocument, $request->user());
        } catch (LegalDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.legal.index')
            ->with('success', "{$published->type->label()} version {$published->version} publicada.");
    }

    public function destroy(
        LegalDocument $legalDocument,
        LegalDocumentService $documents,
    ): RedirectResponse {
        try {
            $documents->discardDraft($legalDocument);
        } catch (LegalDocumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.legal.index')->with('success', 'Borrador descartado.');
    }
}
