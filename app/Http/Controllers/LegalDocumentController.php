<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Support\Legal\LegalDocumentService;
use App\Support\Legal\LegalReadinessService;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function terms(
        LegalDocumentService $documents,
        LegalReadinessService $readiness,
    ): View {
        return $this->show(LegalDocumentType::Terms, $documents, $readiness);
    }

    public function privacy(
        LegalDocumentService $documents,
        LegalReadinessService $readiness,
    ): View {
        return $this->show(LegalDocumentType::Privacy, $documents, $readiness);
    }

    private function show(
        LegalDocumentType $type,
        LegalDocumentService $documents,
        LegalReadinessService $readiness,
    ): View {
        $document = $documents->active($type);
        abort_if($document === null, 404);

        return view('shop.legal', [
            'legalDocument' => $document,
            'legalBody' => Str::markdown($document->body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'isDemoMode' => $readiness->isDemoMode(),
        ]);
    }
}
