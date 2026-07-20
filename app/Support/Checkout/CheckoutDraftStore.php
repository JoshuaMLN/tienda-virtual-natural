<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use ValueError;

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
        ) {
            return null;
        }

        $addressId = isset($data['address_id']) ? (int) $data['address_id'] : null;

        if ($addressId !== null && $addressId <= 0) {
            return null;
        }

        try {
            $deliveryMethod = isset($data['delivery_method'])
                ? DeliveryMethod::from((string) $data['delivery_method'])
                : null;
        } catch (ValueError) {
            return null;
        }

        if (
            ($deliveryMethod === DeliveryMethod::HomeDelivery && $addressId === null)
            || ($deliveryMethod === DeliveryMethod::Pickup && $addressId !== null)
        ) {
            return null;
        }

        $deliveryQuote = CheckoutDeliverySnapshot::fromArray(
            is_array($data['delivery_quote'] ?? null) ? $data['delivery_quote'] : null,
        );

        if (
            $deliveryQuote !== null
            && ($deliveryQuote->method !== $deliveryMethod || $deliveryQuote->addressId !== $addressId)
        ) {
            $deliveryQuote = null;
        }

        return new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $data['contact_name'],
            contactPhone: $data['contact_phone'],
            addressId: $addressId,
            deliveryMethod: $deliveryMethod,
            deliveryQuote: $deliveryQuote,
        );
    }

    public function put(
        User $user,
        string $contactName,
        string $contactPhone,
        ?int $addressId,
        DeliveryMethod $deliveryMethod,
        CheckoutDeliverySnapshot $deliveryQuote,
    ): CheckoutDraft {
        $draft = new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $contactName,
            contactPhone: $contactPhone,
            addressId: $addressId,
            deliveryMethod: $deliveryMethod,
            deliveryQuote: $deliveryQuote,
        );

        $this->session->put(self::SESSION_KEY, $draft->toArray());

        return $draft;
    }
}
