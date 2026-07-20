<?php

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\SaveCheckoutContactAddressRequest;
use App\Support\Addresses\AddressLimitExceededException;
use App\Support\Checkout\CheckoutContactAddressService;
use App\Support\Checkout\CheckoutDeliveryUnavailableException;
use App\Support\Checkout\CheckoutReadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

class CheckoutContactAddressController extends Controller
{
    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutContactAddressService $contactAddressService,
    ) {}

    public function __invoke(SaveCheckoutContactAddressRequest $request): RedirectResponse
    {
        if ($this->checkoutReadService->current() === null) {
            return redirect()->route('checkout.index');
        }

        try {
            $this->contactAddressService->save($request->user(), $request->validated());
        } catch (AddressLimitExceededException $exception) {
            return back()
                ->withInput()
                ->withErrors(['address_choice' => $exception->getMessage()], 'checkout');
        } catch (ModelNotFoundException) {
            return back()
                ->withInput()
                ->withErrors([
                    'address_choice' => 'La direccion seleccionada ya no esta disponible.',
                ], 'checkout');
        } catch (CheckoutDeliveryUnavailableException $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'delivery_method' => $exception->getMessage(),
                ], 'checkout');
        }

        return redirect()
            ->route('checkout.index')
            ->with('status', 'checkout-contact-address-saved');
    }
}
