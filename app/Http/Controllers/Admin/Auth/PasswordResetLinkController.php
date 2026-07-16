<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\RolePasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly RolePasswordResetService $passwords) {}

    public function create(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->passwords->sendResetLink(UserRole::Admin, $request->validated('email'));

        return back()->with(
            'status',
            'Si el correo pertenece a una cuenta administrativa, recibiras un enlace de recuperacion.'
        );
    }
}
