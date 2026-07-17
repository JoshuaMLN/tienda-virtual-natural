<?php

namespace App\Support\Checkout;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Geography\LimaCallaoUbigeoCatalog;

class CheckoutFormDataService
{
    public function __construct(
        private readonly CheckoutDraftStore $draftStore,
        private readonly LimaCallaoUbigeoCatalog $ubigeoCatalog,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $addresses = $user->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $draft = $this->draftStore->get($user);
        $selectedAddress = $draft
            ? $addresses->firstWhere('id', $draft->addressId)
            : null;
        $selectedAddress ??= $addresses->firstWhere('is_default', true) ?? $addresses->first();

        return [
            'contact' => [
                'name' => $draft?->contactName ?? $user->name,
                'email' => $user->email,
                'phone' => $draft?->contactPhone ?? ($user->phone ?? ''),
            ],
            'addresses' => $addresses
                ->map(fn (CustomerAddress $address): array => $this->addressToArray($address))
                ->values()
                ->all(),
            'selected_address_id' => $selectedAddress?->getKey(),
            'address_count' => $addresses->count(),
            'address_limit' => CustomerAddressService::MAX_ADDRESSES,
            'can_create_address' => $addresses->count() < CustomerAddressService::MAX_ADDRESSES,
            'is_first_address' => $addresses->isEmpty(),
            'location_catalog' => $this->ubigeoCatalog->selectionCatalog(),
        ];
    }

    /** @return array<string, mixed> */
    private function addressToArray(CustomerAddress $address): array
    {
        return [
            'id' => (int) $address->getKey(),
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'department' => $address->department,
            'province' => $address->province,
            'district' => $address->district,
            'ubigeo' => $address->ubigeo,
            'address_line' => $address->address_line,
            'reference' => $address->reference,
            'is_default' => (bool) $address->is_default,
        ];
    }
}
