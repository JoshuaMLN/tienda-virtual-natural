<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminResetPasswordRequest;
use App\Services\Auth\RolePasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private readonly RolePasswordResetService $passwords) {}

    public function create(Request $request, string $token): View
    {
        return view('admin.auth.reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $token,
        ]);
    }

    public function store(AdminResetPasswordRequest $request): RedirectResponse
    {
        if (! $this->passwords->reset(UserRole::Admin, $request->validated())) {
            throw ValidationException::withMessages([
                'email' => 'El enlace de recuperacion no es valido o ha vencido.',
            ])->errorBag('resetPassword');
        }

        return redirect()
            ->route('admin.login')
            ->with('status', 'Tu contrasena administrativa fue restablecida.');
    }
}
