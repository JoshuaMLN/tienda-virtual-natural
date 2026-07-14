<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user()->load('socialAccounts');

        return view('account.security', [
            'user' => $user,
            'googleAccount' => $user->socialAccounts
                ->firstWhere('provider', SocialAccount::PROVIDER_GOOGLE),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('status', 'password-updated');
    }
}
