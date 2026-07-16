<?php

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

class AdminPassword
{
    public static function rule(): Password
    {
        return Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers();
    }
}
