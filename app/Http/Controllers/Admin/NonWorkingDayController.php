<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNonWorkingDayRequest;
use App\Models\NonWorkingDay;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class NonWorkingDayController extends Controller
{
    public function store(StoreNonWorkingDayRequest $request): RedirectResponse
    {
        try {
            NonWorkingDay::query()->create($request->validated());
        } catch (UniqueConstraintViolationException) {
            return back()
                ->withErrors(['date' => 'Esta fecha ya se encuentra registrada.'], 'nonWorkingDay')
                ->withInput();
        }

        return back()->with('success', 'Fecha sin atencion agregada al calendario.');
    }

    public function destroy(NonWorkingDay $nonWorkingDay): RedirectResponse
    {
        $nonWorkingDay->delete();

        return back()->with('success', 'Fecha retirada del calendario de cierres.');
    }
}
