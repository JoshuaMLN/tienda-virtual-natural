<?php

namespace App\Support\Checkout;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

class CheckoutDraftStore
{
    private const SESSION_KEY = 'checkout.draft';

    public function __construct(
        private readonly Session $session,
    ) {}

    public function get(User $user): ?CheckoutDraft
    {
        $data = $this->session->get(self::SESSION_KEY);

        if (
            ! is_array($data)
            || (int) ($data['user_id'] ?? 0) !== (int) $user->getKey()
            || ! is_string($data['contact_name'] ?? null)
            || ! is_string($data['contact_phone'] ?? null)
            || (int) ($data['address_id'] ?? 0) <= 0
        ) {
            return null;
        }

        return new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $data['contact_name'],
            contactPhone: $data['contact_phone'],
            addressId: (int) $data['address_id'],
        );
    }

    public function put(
        User $user,
        string $contactName,
        string $contactPhone,
        int $addressId,
    ): CheckoutDraft {
        $draft = new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $contactName,
            contactPhone: $contactPhone,
            addressId: $addressId,
        );

        $this->session->put(self::SESSION_KEY, $draft->toArray());

        return $draft;
    }
}
