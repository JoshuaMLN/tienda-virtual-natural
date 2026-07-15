<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PreserveCheckoutAfterVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($request->session()->pull(
                PreserveCheckoutAfterVerification::SESSION_KEY,
                route('account.profile')
            ));
        }

        return view('auth.verify-email');
    }
}
