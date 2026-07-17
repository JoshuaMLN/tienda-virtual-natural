<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLegalSettingsRequest;
use App\Models\LegalDocument;
use App\Models\Setting;
use App\Support\Legal\LegalReadinessService;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LegalSettingsController extends Controller
{
    public function index(
        StorefrontSettings $settings,
        LegalReadinessService $readiness,
    ): View {
        return view('admin.legal.index', [
            'storeSettings' => $settings,
            'readiness' => $readiness,
            'missingRequirements' => $readiness->missingRequirements(),
            'documents' => LegalDocument::query()
                ->with(['creator', 'publisher'])
                ->orderBy('type')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy(fn (LegalDocument $document): string => $document->type->value),
            'documentTypes' => LegalDocumentType::cases(),
        ]);
    }

    public function update(
        UpdateLegalSettingsRequest $request,
        LegalReadinessService $readiness,
    ): RedirectResponse {
        $validated = $request->validated();
        $requestedLiveSales = (bool) $validated['live_sales_enabled'];
        $liveSalesEnabled = false;

        DB::transaction(function () use ($validated, $requestedLiveSales, $readiness, &$liveSalesEnabled): void {
            Setting::setValues([
                Setting::LEGAL_TRADE_NAME => $validated['legal_trade_name'],
                Setting::LEGAL_PROVIDER_NAME => $validated['legal_provider_name'] ?? '',
                Setting::LEGAL_TAX_ID => $validated['legal_tax_id'] ?? '',
                Setting::LEGAL_FISCAL_ADDRESS => $validated['legal_fiscal_address'] ?? '',
                Setting::LEGAL_COMPLAINTS_BOOK_URL => $validated['legal_complaints_book_url'] ?? '',
                Setting::INCIDENT_REPORT_HOURS => (int) $validated['incident_report_hours'],
                Setting::REFUND_PROCESSING_BUSINESS_DAYS => (int) $validated['refund_processing_business_days'],
                Setting::DELIVERY_ATTEMPTS_PER_CYCLE => (int) $validated['delivery_attempts_per_cycle'],
                Setting::DELIVERY_MAX_AUTOMATIC_CYCLES => (int) $validated['delivery_max_automatic_cycles'],
                Setting::RESHIPMENT_PAYMENT_DAYS => (int) $validated['reshipment_payment_days'],
                Setting::PICKUP_HOLD_DAYS => (int) $validated['pickup_hold_days'],
            ]);

            Setting::clearLocalCache();
            $liveSalesEnabled = $requestedLiveSales && $readiness->canEnableLiveSales();
            Setting::setValue(Setting::LIVE_SALES_ENABLED, $liveSalesEnabled ? '1' : '0');
        });

        Setting::clearLocalCache();

        if ($requestedLiveSales && ! $liveSalesEnabled) {
            return back()->with('warning', 'La configuracion se guardo, pero las ventas reales siguen deshabilitadas. Completa la identidad y publica ambos documentos con los valores vigentes.');
        }

        return back()->with('success', 'Configuracion legal actualizada.');
    }
}
