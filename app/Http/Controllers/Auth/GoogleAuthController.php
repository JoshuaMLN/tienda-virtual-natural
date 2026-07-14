<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SocialAccountException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmGoogleLinkRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Auth\SocialAccountService;
use App\Support\Auth\GoogleProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    private const MODE_LOGIN = 'login';

    private const MODE_LINK = 'link';

    private const MODE_SESSION_KEY = 'auth.google.mode';

    private const PENDING_SESSION_KEY = 'auth.google.pending_link';

    private const PENDING_MINUTES = 10;

    public function __construct(private readonly SocialAccountService $accounts) {}

    public function redirect(Request $request): RedirectResponse
    {
        return $this->beginGoogleFlow($request, self::MODE_LOGIN);
    }

    public function link(Request $request): RedirectResponse
    {
        $alreadyLinked = $request->user()
            ->socialAccounts()
            ->where('provider', SocialAccount::PROVIDER_GOOGLE)
            ->exists();

        if ($alreadyLinked) {
            return back()->with('status', 'google-already-linked');
        }

        return $this->beginGoogleFlow($request, self::MODE_LINK);
    }

    public function callback(Request $request): RedirectResponse
    {
        $mode = $request->session()->pull(self::MODE_SESSION_KEY);

        if (! in_array($mode, [self::MODE_LOGIN, self::MODE_LINK], true)) {
            return $this->googleError(
                $request->user() ? 'account.security' : 'login',
                'La solicitud de Google vencio o no corresponde a esta sesion. Intentalo nuevamente.'
            );
        }

        if ($request->filled('error')) {
            return $this->googleError(
                $mode === self::MODE_LINK ? 'account.security' : 'login',
                'No se completo el acceso con Google. Puedes intentarlo nuevamente.'
            );
        }

        try {
            $profile = GoogleProfile::fromProvider(Socialite::driver('google')->user());
        } catch (SocialAccountException $exception) {
            return $this->googleError(
                $mode === self::MODE_LINK ? 'account.security' : 'login',
                $exception->getMessage()
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->googleError(
                $mode === self::MODE_LINK ? 'account.security' : 'login',
                'No pudimos validar tu cuenta con Google. Intentalo nuevamente.'
            );
        }

        if ($mode === self::MODE_LINK) {
            return $this->completeAuthenticatedLink($request, $profile);
        }

        if ($request->user() !== null) {
            return $this->googleError(
                'account.security',
                'Ya tienes una sesion iniciada. Vincula Google desde la seccion Seguridad.'
            );
        }

        if (($linkedUser = $this->accounts->linkedUser($profile)) !== null) {
            return $this->authenticate($request, $linkedUser);
        }

        if ($this->accounts->userWithEmail($profile) !== null) {
            $request->session()->put(self::PENDING_SESSION_KEY, [
                ...$profile->toSession(),
                'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
            ]);

            return redirect()->route('auth.google.confirm');
        }

        try {
            $user = $this->accounts->createUser($profile);
        } catch (SocialAccountException $exception) {
            return $this->googleError('login', $exception->getMessage());
        }

        return $this->authenticate($request, $user);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return $this->googleError(
                'login',
                'La confirmacion de vinculacion vencio. Inicia el proceso con Google nuevamente.'
            );
        }

        return view('auth.confirm-google-link', [
            'email' => $profile->email,
        ]);
    }

    public function confirmLink(ConfirmGoogleLinkRequest $request): RedirectResponse
    {
        $profile = $this->pendingProfile($request);

        if ($profile === null) {
            return $this->googleError(
                'login',
                'La confirmacion de vinculacion vencio. Inicia el proceso con Google nuevamente.'
            );
        }

        $user = $this->accounts->userWithEmail($profile);

        if ($user === null || $user->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            return back()->withErrors([
                'password' => 'La contrasena no corresponde a la cuenta existente.',
            ], 'googleLink');
        }

        try {
            $this->accounts->link($user, $profile);
        } catch (SocialAccountException $exception) {
            return back()->withErrors(['google' => $exception->getMessage()], 'googleLink');
        }

        $request->session()->forget(self::PENDING_SESSION_KEY);

        return $this->authenticate($request, $user, 'Google se vinculo correctamente a tu cuenta.');
    }

    public function unlink(Request $request): RedirectResponse
    {
        try {
            $this->accounts->unlinkGoogle($request->user());
        } catch (SocialAccountException $exception) {
            return back()->withErrors(['google' => $exception->getMessage()], 'google');
        }

        return back()->with('status', 'google-unlinked');
    }

    private function beginGoogleFlow(Request $request, string $mode): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SESSION_KEY);
        $request->session()->put(self::MODE_SESSION_KEY, $mode);

        return Socialite::driver('google')->redirect();
    }

    private function completeAuthenticatedLink(Request $request, GoogleProfile $profile): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->googleError(
                'login',
                'Tu sesion vencio antes de terminar la vinculacion. Inicia sesion e intentalo nuevamente.'
            );
        }

        try {
            $this->accounts->link($user, $profile);
        } catch (SocialAccountException $exception) {
            return $this->googleError('account.security', $exception->getMessage());
        }

        return redirect()->route('account.security')->with('status', 'google-linked');
    }

    private function authenticate(Request $request, User $user, ?string $status = null): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();

        $fallback = $user->hasVerifiedEmail()
            ? route('account.profile')
            : route('verification.notice');

        $response = redirect()->intended($fallback);

        return $status === null ? $response : $response->with('status', $status);
    }

    private function pendingProfile(Request $request): ?GoogleProfile
    {
        $pending = $request->session()->get(self::PENDING_SESSION_KEY);

        if (! is_array($pending) || (int) ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }

        try {
            return GoogleProfile::fromSession($pending);
        } catch (SocialAccountException) {
            $request->session()->forget(self::PENDING_SESSION_KEY);

            return null;
        }
    }

    private function googleError(string $route, string $message): RedirectResponse
    {
        return redirect()->route($route)->withErrors(['google' => $message], 'google');
    }
}
