<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Cart\CartMergeCoordinator;
use App\Support\Cart\SessionCartStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CartMergeCoordinator $cartMerge,
        private readonly SessionCartStorage $sessionCart,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $this->cartMerge->mergeFor($request->user());

        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && ! $this->isAdministrativeDestination($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->route('account.profile');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $preservedGuestItems = [];
        $preservedWarnings = [];
        $user = $request->user();

        if ($user && ! $this->cartMerge->mergeFor($user)) {
            $preservedGuestItems = $this->sessionCart->all();
            $preservedWarnings = $this->sessionCart->warnings();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        foreach ($preservedGuestItems as $productId => $quantity) {
            $this->sessionCart->set($productId, $quantity);
        }

        $this->sessionCart->addWarnings($preservedWarnings);

        return redirect()
            ->route('shop.index')
            ->with('status', 'Sesion cerrada correctamente.');
    }

    private function isAdministrativeDestination(string $destination): bool
    {
        return str_starts_with('/'.ltrim((string) parse_url($destination, PHP_URL_PATH), '/'), '/admin');
    }
}
