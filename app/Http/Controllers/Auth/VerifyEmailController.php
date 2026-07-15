<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PreserveCheckoutAfterVerification;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        $destination = $request->session()->pull(
            PreserveCheckoutAfterVerification::SESSION_KEY,
            route('account.profile')
        );

        return redirect()
            ->to($destination)
            ->with('status', 'Correo electronico verificado correctamente.');
    }
}
