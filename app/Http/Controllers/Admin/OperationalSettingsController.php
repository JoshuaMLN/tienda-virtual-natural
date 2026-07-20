<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDeliveryDistrictRequest;
use App\Http\Requests\Admin\UpdateOperationalSettingsRequest;
use App\Models\DeliveryDistrict;
use App\Models\NonWorkingDay;
use App\Models\Setting;
use App\Support\Money\Money;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationalSettingsController extends Controller
{
    public function edit(Request $request, StorefrontSettings $settings): View
    {
        $query = DeliveryDistrict::query()
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim($request->string('q')->toString());

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('district', 'like', "%{$term}%")
                        ->orWhere('province', 'like', "%{$term}%")
                        ->orWhere('ubigeo', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('province'), function (Builder $query) use ($request): void {
                $query->where('province_code', $request->string('province')->toString());
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('is_active', $request->string('status')->toString() === 'active');
            });

        return view('admin.settings.edit', [
            'districts' => $query
                ->orderBy('province')
                ->orderBy('district')
                ->paginate(15)
                ->withQueryString(),
            'districtSummary' => [
                'total' => DeliveryDistrict::query()->count(),
                'active' => DeliveryDistrict::query()->active()->count(),
                'inactive' => DeliveryDistrict::query()->where('is_active', false)->count(),
            ],
            'provinces' => DeliveryDistrict::query()
                ->select(['province_code', 'province'])
                ->distinct()
                ->orderBy('province')
                ->get(),
            'storeSettings' => $settings,
            'nonWorkingDays' => NonWorkingDay::query()
                ->whereDate('date', '>=', today())
                ->orderBy('date')
                ->get(),
        ]);
    }

    public function update(UpdateOperationalSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::setValues([
            Setting::CONTACT_WHATSAPP => $validated['contact_whatsapp'],
            Setting::CONTACT_EMAIL => $validated['contact_email'],
            Setting::CONTACT_PHONE => $validated['contact_phone'] ?? '',
            Setting::BUSINESS_HOURS_WEEKDAYS_OPEN => $validated['business_hours_weekdays_open'],
            Setting::BUSINESS_HOURS_WEEKDAYS_CLOSE => $validated['business_hours_weekdays_close'],
            Setting::BUSINESS_HOURS_SATURDAY_OPEN => $validated['business_hours_saturday_open'] ?? '',
            Setting::BUSINESS_HOURS_SATURDAY_CLOSE => $validated['business_hours_saturday_close'] ?? '',
            Setting::BUSINESS_HOURS_SUNDAY_OPEN => $validated['business_hours_sunday_open'] ?? '',
            Setting::BUSINESS_HOURS_SUNDAY_CLOSE => $validated['business_hours_sunday_close'] ?? '',
            Setting::FREE_SHIPPING_THRESHOLD => Money::fromDecimal($validated['free_shipping_threshold'])->decimal(),
            Setting::STOCK_RESERVATION_MINUTES => (int) $validated['stock_reservation_minutes'],
            Setting::DELIVERY_BUSINESS_DAYS_MIN => (int) $validated['delivery_business_days_min'],
            Setting::DELIVERY_BUSINESS_DAYS_MAX => (int) $validated['delivery_business_days_max'],
            Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN => (int) $validated['pickup_preparation_business_days_min'],
            Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX => (int) $validated['pickup_preparation_business_days_max'],
            Setting::PICKUP_ADDRESS => $validated['pickup_address'] ?? '',
        ]);

        return back()->with('success', 'Configuracion operativa actualizada.');
    }

    public function updateDistrict(
        UpdateDeliveryDistrictRequest $request,
        DeliveryDistrict $deliveryDistrict
    ): RedirectResponse {
        $validated = $request->validated();
        $usesDefaultWindow = (bool) $validated['use_default_delivery_window'];

        $deliveryDistrict->update([
            'shipping_fee' => Money::fromDecimal($validated['shipping_fee'])->decimal(),
            'delivery_business_days_min' => $usesDefaultWindow ? null : (int) $validated['delivery_business_days_min'],
            'delivery_business_days_max' => $usesDefaultWindow ? null : (int) $validated['delivery_business_days_max'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return back()->with('success', "Tarifa y plazo de {$deliveryDistrict->district} actualizados.");
    }
}
