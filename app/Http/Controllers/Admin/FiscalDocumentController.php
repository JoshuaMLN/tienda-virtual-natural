<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FiscalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnulFiscalDocumentRequest;
use App\Http\Requests\Admin\CorrectFiscalDocumentRequest;
use App\Http\Requests\Admin\SendFiscalDocumentRequest;
use App\Http\Requests\Admin\StoreFiscalDocumentRequest;
use App\Http\Requests\Admin\StoreRelatedFiscalDocumentRequest;
use App\Jobs\SendFiscalDocumentEmail;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Support\Orders\CustomerFiscalDocumentPresenter;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiscalDocumentController extends Controller
{
    public function __construct(
        private readonly FiscalDocumentService $documents,
        private readonly CustomerFiscalDocumentPresenter $presenter,
    ) {}

    public function store(StoreFiscalDocumentRequest $request, Order $order): RedirectResponse
    {
        $path = $request->file('pdf')->storeAs(
            'fiscal-documents/'.$order->getKey(),
            Str::uuid().'.pdf',
            'local',
        );

        try {
            $document = $this->documents->registerSaleDocument(
                $order,
                $order->fiscal_document_type,
                $request->string('series')->toString(),
                $request->string('correlative')->toString(),
                CarbonImmutable::createFromFormat('Y-m-d', $request->string('issued_at')->toString(), config('app.timezone'))->startOfDay(),
                $path,
                registrar: $request->user(),
            );
        } catch (DomainException|QueryException $exception) {
            Storage::disk('local')->delete($path);

            return back()->withInput()->with('error', $exception instanceof QueryException
                ? 'La serie y correlativo ya pertenecen a otro comprobante.'
                : $exception->getMessage());
        }

        return redirect()->route('admin.orders.show', $order->code)
            ->with('success', "Comprobante {$document->series}-{$document->correlative} registrado correctamente.");
    }

    public function download(Order $order, FiscalDocument $document): StreamedResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);
        $path = trim((string) $document->pdf_path);
        abort_if($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf', 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $this->presenter->downloadName($document), [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function send(SendFiscalDocumentRequest $request, Order $order, FiscalDocument $document): RedirectResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);

        try {
            $this->documents->assertCanBeDelivered($document);

            if (! Storage::disk('local')->exists($document->pdf_path)) {
                throw new DomainException('El PDF vigente del comprobante ya no esta disponible.');
            }
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $admin = $request->user();

        SendFiscalDocumentEmail::dispatch(
            $document->getKey(),
            $admin?->getKey(),
            $admin?->name,
            $admin?->email,
        );

        return back()->with('success', 'El comprobante fue programado para envio al correo fiscal del pedido.');
    }

    public function correct(CorrectFiscalDocumentRequest $request, Order $order, FiscalDocument $document): RedirectResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);

        $path = $request->hasFile('pdf')
            ? $request->file('pdf')->storeAs('fiscal-documents/'.$order->getKey(), Str::uuid().'.pdf', 'local')
            : null;
        $values = [
            'series' => $request->string('series')->toString(),
            'correlative' => $request->string('correlative')->toString(),
            'issued_at' => CarbonImmutable::createFromFormat('Y-m-d', $request->string('issued_at')->toString(), config('app.timezone'))->startOfDay(),
        ];

        try {
            $this->documents->correct($document, $path, $values, $request->string('reason')->toString(), $request->user());
        } catch (DomainException|QueryException $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            return back()->withInput()->with('error', $exception instanceof QueryException ? 'La serie y correlativo ya pertenecen a otro comprobante.' : $exception->getMessage());
        }

        return back()->with('success', 'Comprobante corregido con historial privado.');
    }

    public function annul(AnnulFiscalDocumentRequest $request, Order $order, FiscalDocument $document): RedirectResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);
        $this->documents->annul($document, $request->string('reason')->toString(), $request->user());

        return back()->with('success', 'Anulacion fiscal registrada.');
    }

    public function storeRelated(StoreRelatedFiscalDocumentRequest $request, Order $order, FiscalDocument $document): RedirectResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);
        $path = $request->file('pdf')->storeAs('fiscal-documents/'.$order->getKey(), Str::uuid().'.pdf', 'local');
        try {
            $type = FiscalDocumentType::from($request->string('type')->toString());
            $this->documents->registerRelatedDocument($document, $type, $request->string('series')->toString(), $request->string('correlative')->toString(), CarbonImmutable::createFromFormat('Y-m-d', $request->string('issued_at')->toString(), config('app.timezone'))->startOfDay(), $path, registrar: $request->user());
        } catch (DomainException|QueryException $exception) {
            Storage::disk('local')->delete($path);

            return back()->with('error', $exception instanceof QueryException ? 'La serie y correlativo ya pertenecen a otro comprobante.' : $exception->getMessage());
        }

        return back()->with('success', 'Nota fiscal relacionada registrada.');
    }

    public function storeReplacement(StoreFiscalDocumentRequest $request, Order $order, FiscalDocument $document): RedirectResponse
    {
        abort_unless($document->order_id === $order->getKey(), 404);
        $path = $request->file('pdf')->storeAs('fiscal-documents/'.$order->getKey(), Str::uuid().'.pdf', 'local');
        try {
            $this->documents->registerReplacement($document, $request->string('series')->toString(), $request->string('correlative')->toString(), CarbonImmutable::createFromFormat('Y-m-d', $request->string('issued_at')->toString(), config('app.timezone'))->startOfDay(), $path, $request->user());
        } catch (DomainException|QueryException $exception) {
            Storage::disk('local')->delete($path);

            return back()->with('error', $exception instanceof QueryException ? 'La serie y correlativo ya pertenecen a otro comprobante.' : $exception->getMessage());
        }

        return back()->with('success', 'Comprobante de reemplazo registrado.');
    }
}
