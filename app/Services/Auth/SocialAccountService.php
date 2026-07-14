<?php

namespace App\Services\Auth;

use App\Exceptions\SocialAccountException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Auth\GoogleProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialAccountService
{
    public function linkedUser(GoogleProfile $profile): ?User
    {
        return SocialAccount::query()
            ->with('user')
            ->where('provider', SocialAccount::PROVIDER_GOOGLE)
            ->where('provider_user_id', $profile->id)
            ->first()
            ?->user;
    }

    public function userWithEmail(GoogleProfile $profile): ?User
    {
        return User::query()->where('email', $profile->email)->first();
    }

    public function createUser(GoogleProfile $profile): User
    {
        return DB::transaction(function () use ($profile): User {
            $linkedUser = $this->linkedUser($profile);

            if ($linkedUser !== null) {
                return $linkedUser;
            }

            if ($this->userWithEmail($profile) !== null) {
                throw SocialAccountException::existingEmail();
            }

            $user = User::query()->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'phone' => null,
                'password' => null,
                'terms_accepted_at' => now(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $this->createSocialAccount($user, $profile);

            return $user;
        });
    }

    public function link(User $user, GoogleProfile $profile): SocialAccount
    {
        if (! hash_equals(Str::lower(trim($user->email)), $profile->email)) {
            throw SocialAccountException::emailMismatch();
        }

        return DB::transaction(function () use ($user, $profile): SocialAccount {
            $identity = SocialAccount::query()
                ->where('provider', SocialAccount::PROVIDER_GOOGLE)
                ->where('provider_user_id', $profile->id)
                ->lockForUpdate()
                ->first();

            if ($identity !== null && $identity->user_id !== $user->getKey()) {
                throw SocialAccountException::identityInUse();
            }

            $current = $user->socialAccounts()
                ->where('provider', SocialAccount::PROVIDER_GOOGLE)
                ->lockForUpdate()
                ->first();

            if ($current !== null && $current->provider_user_id !== $profile->id) {
                throw SocialAccountException::providerAlreadyLinked();
            }

            $account = $current ?? $this->createSocialAccount($user, $profile);

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            return $account;
        });
    }

    public function unlinkGoogle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $accounts = $user->socialAccounts()->lockForUpdate()->get();
            $google = $accounts->firstWhere('provider', SocialAccount::PROVIDER_GOOGLE);

            if ($google === null) {
                throw SocialAccountException::missingProvider();
            }

            if ($user->password === null && $accounts->count() === 1) {
                throw SocialAccountException::lastAuthenticationMethod();
            }

            $google->delete();
        });
    }

    private function createSocialAccount(User $user, GoogleProfile $profile): SocialAccount
    {
        return $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => $profile->id,
        ]);
    }
}
