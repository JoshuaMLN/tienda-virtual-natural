<?php

namespace App\Support\Checkout;

use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\User;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use App\Support\Money\Money;
use Illuminate\Support\Collection;

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

        if ($draft === null || $draft->deliveryMethod?->value === 'pickup') {
            $selectedAddress ??= $addresses->firstWhere('is_default', true) ?? $addresses->first();
        }

        $deliveryDistricts = DeliveryDistrict::query()
            ->get()
            ->keyBy('ubigeo');

        return [
            'contact' => [
                'name' => $draft?->contactName ?? $user->name,
                'email' => $user->email,
                'phone' => $draft?->contactPhone ?? ($user->phone ?? ''),
            ],
            'addresses' => $addresses
                ->map(fn (CustomerAddress $address): array => $this->addressToArray($address, $deliveryDistricts))
                ->values()
                ->all(),
            'selected_address_id' => $selectedAddress?->getKey(),
            'selected_delivery_method' => $draft?->deliveryMethod,
            'delivery_quote_reference' => $draft?->deliveryQuote?->fingerprint(),
            'address_count' => $addresses->count(),
            'address_limit' => CustomerAddressService::MAX_ADDRESSES,
            'can_create_address' => $addresses->count() < CustomerAddressService::MAX_ADDRESSES,
            'is_first_address' => $addresses->isEmpty(),
            'location_catalog' => $this->deliveryCatalog($deliveryDistricts),
        ];
    }

    /**
     * @param  Collection<string, DeliveryDistrict>  $deliveryDistricts
     * @return array<string, mixed>
     */
    private function addressToArray(CustomerAddress $address, Collection $deliveryDistricts): array
    {
        $deliveryDistrict = $deliveryDistricts->get($address->ubigeo);
        $deliveryAvailable = $deliveryDistrict?->is_active === true;

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
            'delivery_available' => $deliveryAvailable,
            'formatted_shipping_fee' => $deliveryAvailable
                ? Money::fromDecimal($deliveryDistrict->shipping_fee)->formatted()
                : null,
        ];
    }

    /**
     * @param  Collection<string, DeliveryDistrict>  $deliveryDistricts
     * @return array<int|string, array<string, mixed>>
     */
    private function deliveryCatalog(Collection $deliveryDistricts): array
    {
        $catalog = $this->ubigeoCatalog->selectionCatalog();

        foreach ($catalog as &$province) {
            foreach ($province['districts'] as &$district) {
                $deliveryDistrict = $deliveryDistricts->get($district['code']);
                $district['delivery_available'] = $deliveryDistrict?->is_active === true;
                $district['formatted_shipping_fee'] = $deliveryDistrict !== null
                    ? Money::fromDecimal($deliveryDistrict->shipping_fee)->formatted()
                    : null;
            }
            unset($district);
        }
        unset($province);

        return $catalog;
    }
}
