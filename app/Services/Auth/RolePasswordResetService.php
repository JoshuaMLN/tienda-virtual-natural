<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RolePasswordResetService
{
    public function sendResetLink(UserRole $role, string $email): void
    {
        $user = $this->userFor($role, $email);

        if ($user === null) {
            return;
        }

        $token = Password::broker()->createToken($user);
        $notification = $role === UserRole::Admin
            ? new AdminResetPasswordNotification($token)
            : new ResetPasswordNotification($token);

        $user->notify($notification);
    }

    public function reset(UserRole $role, array $credentials): bool
    {
        $user = $this->userFor($role, (string) $credentials['email']);
        $token = (string) $credentials['token'];

        if ($user === null || ! Password::broker()->tokenExists($user, $token)) {
            return false;
        }

        $user->forceFill([
            'password' => (string) $credentials['password'],
            'remember_token' => Str::random(60),
        ])->save();

        Password::broker()->deleteToken($user);
        event(new PasswordReset($user));

        return true;
    }

    private function userFor(UserRole $role, string $email): ?User
    {
        return User::query()
            ->where('role', $role->value)
            ->where('email', Str::lower(trim($email)))
            ->first();
    }
}
