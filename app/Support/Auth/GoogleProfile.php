<?php

namespace App\Support\Auth;

use App\Exceptions\SocialAccountException;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;

final readonly class GoogleProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}

    public static function fromProvider(ProviderUser $user): self
    {
        $raw = $user->getRaw();
        $verified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (! $verified) {
            throw SocialAccountException::unverifiedGoogleEmail();
        }

        return self::fromValues(
            (string) $user->getId(),
            (string) $user->getName(),
            (string) $user->getEmail(),
        );
    }

    /**
     * @param  array{id?: mixed, name?: mixed, email?: mixed}  $data
     */
    public static function fromSession(array $data): self
    {
        return self::fromValues(
            (string) ($data['id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['email'] ?? ''),
        );
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function toSession(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    private static function fromValues(string $id, string $name, string $email): self
    {
        $id = trim($id);
        $email = Str::lower(trim($email));

        if ($id === '' || mb_strlen($id) > 191 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SocialAccountException::invalidGoogleResponse();
        }

        $name = Str::squish($name);
        $name = $name !== '' ? $name : Str::before($email, '@');

        return new self($id, Str::limit($name, 120, ''), $email);
    }
}
