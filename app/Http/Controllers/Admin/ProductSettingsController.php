<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.products.settings', [
            'publicStockDisplayThreshold' => Setting::publicStockDisplayThreshold(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'public_stock_display_threshold' => ['required', 'integer', 'min:0', 'max:999999'],
            ],
            [
                'public_stock_display_threshold.required' => 'Ingresa el umbral publico de disponibilidad.',
                'public_stock_display_threshold.integer' => 'El umbral publico debe ser un numero entero.',
                'public_stock_display_threshold.min' => 'El umbral publico no puede ser negativo.',
                'public_stock_display_threshold.max' => 'El umbral publico es demasiado alto.',
            ],
            [
                'public_stock_display_threshold' => 'umbral publico de disponibilidad',
            ]
        );

        Setting::setValue(
            Setting::PUBLIC_STOCK_DISPLAY_THRESHOLD,
            (int) $validated['public_stock_display_threshold']
        );

        return back()->with('success', 'Configuracion de productos actualizada.');
    }
}
