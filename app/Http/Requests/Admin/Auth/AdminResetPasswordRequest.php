<?php

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\Auth\AdminPassword;

class AdminResetPasswordRequest extends ResetPasswordRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['required', 'confirmed', AdminPassword::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'password.min' => 'La contrasena debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contrasena debe incluir mayusculas y minusculas.',
            'password.letters' => 'La contrasena debe incluir letras.',
            'password.numbers' => 'La contrasena debe incluir numeros.',
        ];
    }
}
