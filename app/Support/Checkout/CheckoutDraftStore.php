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
            || ! in_array((int) ($data['schema_version'] ?? 1), [1, 2, 3], true)
            || (int) ($data['user_id'] ?? 0) !== (int) $user->getKey()
            || ! is_string($data['contact_name'] ?? null)
            || ! is_string($data['contact_phone'] ?? null)
            || trim($data['contact_name']) === ''
            || preg_match('/^9\d{8}$/', $data['contact_phone']) !== 1
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

        $review = CheckoutReviewSnapshot::fromArray(
            is_array($data['review'] ?? null) ? $data['review'] : null,
        );
        $fiscal = CheckoutFiscalData::fromArray(
            is_array($data['fiscal'] ?? null) ? $data['fiscal'] : null,
        );

        if ((int) ($data['schema_version'] ?? 1) < 3 && $fiscal === null && $review !== null) {
            $fiscal = $review->fiscal;
        }

        if (
            $review !== null
            && (
                $deliveryQuote === null
                || $review->userId !== (int) $user->getKey()
                || $review->contactName !== $data['contact_name']
                || $review->customerEmail !== $user->email
                || $review->contactPhone !== $data['contact_phone']
                || ! hash_equals($review->deliveryQuoteReference, $deliveryQuote->fingerprint())
                || $fiscal === null
                || $review->fiscal->toArray() !== $fiscal->toArray()
            )
        ) {
            $review = null;
        }

        return new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $data['contact_name'],
            contactPhone: $data['contact_phone'],
            addressId: $addressId,
            deliveryMethod: $deliveryMethod,
            deliveryQuote: $deliveryQuote,
            fiscal: $fiscal,
            review: $review,
        );
    }

    public function clear(User $user): void
    {
        $draft = $this->get($user);

        if ($draft === null || $draft->userId === (int) $user->getKey()) {
            $this->session->forget(self::SESSION_KEY);
        }
    }

    public function clearIfReviewMatches(User $user, string $reviewReference): void
    {
        $draft = $this->get($user);

        if ($draft?->review !== null
            && hash_equals($draft->review->fingerprint(), $reviewReference)) {
            $this->session->forget(self::SESSION_KEY);
        }
    }

    public function put(
        User $user,
        string $contactName,
        string $contactPhone,
        ?int $addressId,
        DeliveryMethod $deliveryMethod,
        CheckoutDeliverySnapshot $deliveryQuote,
    ): CheckoutDraft {
        $current = $this->get($user);
        $draft = new CheckoutDraft(
            userId: (int) $user->getKey(),
            contactName: $contactName,
            contactPhone: $contactPhone,
            addressId: $addressId,
            deliveryMethod: $deliveryMethod,
            deliveryQuote: $deliveryQuote,
            fiscal: $current?->fiscal,
            review: null,
        );

        $this->session->put(self::SESSION_KEY, $draft->toArray());

        return $draft;
    }

    public function replaceDeliveryQuote(
        User $user,
        CheckoutDeliverySnapshot $deliveryQuote,
    ): ?CheckoutDraft {
        $draft = $this->get($user);

        if (
            $draft === null
            || $draft->deliveryMethod !== $deliveryQuote->method
            || $draft->addressId !== $deliveryQuote->addressId
        ) {
            return null;
        }

        $updated = new CheckoutDraft(
            userId: $draft->userId,
            contactName: $draft->contactName,
            contactPhone: $draft->contactPhone,
            addressId: $draft->addressId,
            deliveryMethod: $draft->deliveryMethod,
            deliveryQuote: $deliveryQuote,
            fiscal: $draft->fiscal,
            review: null,
        );

        $this->session->put(self::SESSION_KEY, $updated->toArray());

        return $updated;
    }

    public function putFiscal(User $user, CheckoutFiscalData $fiscal): ?CheckoutDraft
    {
        $draft = $this->get($user);

        if ($draft === null) {
            return null;
        }

        $review = $draft->review;

        if ($review !== null && $review->fiscal->toArray() !== $fiscal->toArray()) {
            $review = null;
        }

        $updated = new CheckoutDraft(
            userId: $draft->userId,
            contactName: $draft->contactName,
            contactPhone: $draft->contactPhone,
            addressId: $draft->addressId,
            deliveryMethod: $draft->deliveryMethod,
            deliveryQuote: $draft->deliveryQuote,
            fiscal: $fiscal,
            review: $review,
        );

        $this->session->put(self::SESSION_KEY, $updated->toArray());

        return $updated;
    }

    public function putReview(User $user, CheckoutReviewSnapshot $review): ?CheckoutDraft
    {
        $draft = $this->get($user);

        if (
            $draft === null
            || $draft->deliveryQuote === null
            || $review->userId !== $draft->userId
            || $review->contactName !== $draft->contactName
            || $review->customerEmail !== $user->email
            || $review->contactPhone !== $draft->contactPhone
            || ! hash_equals($review->deliveryQuoteReference, $draft->deliveryQuote->fingerprint())
        ) {
            return null;
        }

        $updated = new CheckoutDraft(
            userId: $draft->userId,
            contactName: $draft->contactName,
            contactPhone: $draft->contactPhone,
            addressId: $draft->addressId,
            deliveryMethod: $draft->deliveryMethod,
            deliveryQuote: $draft->deliveryQuote,
            fiscal: $review->fiscal,
            review: $review,
        );

        $this->session->put(self::SESSION_KEY, $updated->toArray());

        return $updated;
    }

    public function putRevalidatedReview(
        User $user,
        CheckoutDeliverySnapshot $deliveryQuote,
        CheckoutReviewSnapshot $review,
        string $expectedReviewReference,
    ): ?CheckoutDraft {
        $draft = $this->get($user);

        if (
            $draft === null
            || $draft->review === null
            || $draft->fiscal === null
            || ! hash_equals($draft->review->fingerprint(), $expectedReviewReference)
            || $draft->deliveryMethod !== $deliveryQuote->method
            || $draft->addressId !== $deliveryQuote->addressId
            || $review->userId !== $draft->userId
            || $review->contactName !== $draft->contactName
            || $review->customerEmail !== $user->email
            || $review->contactPhone !== $draft->contactPhone
            || ! hash_equals($review->deliveryQuoteReference, $deliveryQuote->fingerprint())
            || $review->fiscal->toArray() !== $draft->fiscal->toArray()
        ) {
            return null;
        }

        $updated = new CheckoutDraft(
            userId: $draft->userId,
            contactName: $draft->contactName,
            contactPhone: $draft->contactPhone,
            addressId: $draft->addressId,
            deliveryMethod: $draft->deliveryMethod,
            deliveryQuote: $deliveryQuote,
            fiscal: $draft->fiscal,
            review: $review,
        );

        $this->session->put(self::SESSION_KEY, $updated->toArray());

        return $updated;
    }
}
