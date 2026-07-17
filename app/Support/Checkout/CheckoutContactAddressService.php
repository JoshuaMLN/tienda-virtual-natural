<?php

namespace App\Support\Checkout;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Addresses\CustomerAddressService;

class CheckoutContactAddressService
{
    public function __construct(
        private readonly CustomerAddressService $addressService,
        private readonly CheckoutDraftStore $draftStore,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function save(User $user, array $attributes): CheckoutDraft
    {
        $address = $attributes['address_mode'] === 'new'
            ? $this->createAddress($user, $attributes)
            : $this->ownedAddress($user, (int) $attributes['address_id']);

        return $this->draftStore->put(
            $user,
            $attributes['contact_name'],
            $attributes['contact_phone'],
            (int) $address->getKey(),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function createAddress(User $user, array $attributes): CustomerAddress
    {
        return $this->addressService->create($user, [
            'label' => $attributes['label'],
            'recipient_name' => $attributes['recipient_name'],
            'phone' => $attributes['phone'],
            'province_code' => $attributes['province_code'],
            'district_code' => $attributes['district_code'],
            'address_line' => $attributes['address_line'],
            'reference' => $attributes['reference'] ?? null,
            'is_default' => $attributes['is_default'] ?? false,
        ]);
    }

    private function ownedAddress(User $user, int $addressId): CustomerAddress
    {
        return $user->addresses()
            ->whereKey($addressId)
            ->firstOrFail();
    }
}
