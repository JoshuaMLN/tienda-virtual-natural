<?php

namespace App\Http\Requests\Checkout;

class SaveCheckoutFiscalRequest extends ReviewCheckoutRequest
{
    protected function requiresTermsAcceptance(): bool
    {
        return false;
    }
}
