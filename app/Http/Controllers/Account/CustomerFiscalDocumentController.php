<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\FiscalDocument;
use App\Support\Orders\CustomerFiscalDocumentPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerFiscalDocumentController extends Controller
{
    public function __construct(
        private readonly CustomerFiscalDocumentPresenter $documents,
    ) {}

    public function __invoke(Request $request, string $code, int $document): StreamedResponse
    {
        $fiscalDocument = FiscalDocument::query()
            ->whereKey($document)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $request->user()->getKey())
                ->where('code', strtoupper($code)))
            ->firstOrFail();

        $path = trim((string) $fiscalDocument->pdf_path);

        abort_if($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf', 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404);

        return $disk->download(
            $path,
            $this->documents->downloadName($fiscalDocument),
            [
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
