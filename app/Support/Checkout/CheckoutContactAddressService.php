<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Addresses\CustomerAddressService;
use Illuminate\Support\Facades\DB;

class CheckoutContactAddressService
{
    public function __construct(
        private readonly CustomerAddressService $addressService,
        private readonly CheckoutDraftStore $draftStore,
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutDeliveryService $checkoutDeliveryService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function save(User $user, array $attributes): CheckoutDraft
    {
        [$addressId, $method, $delivery] = DB::transaction(function () use ($user, $attributes): array {
            $checkout = $this->checkoutReadService->current();

            if ($checkout === null) {
                throw new CheckoutDeliveryUnavailableException('Tu carrito esta vacio.');
            }

            $method = DeliveryMethod::from($attributes['delivery_method']);
            $addressId = null;

            if ($method === DeliveryMethod::Pickup) {
                $delivery = $this->checkoutDeliveryService->pickup($checkout);
                $this->assertAcceptedQuote($delivery, $attributes['quote_reference']);
            } elseif ($attributes['address_mode'] === 'new') {
                $preview = $this->checkoutDeliveryService->homeForUbigeo(
                    $attributes['district_code'],
                    $checkout,
                    lockForUpdate: true,
                );
                $this->assertAcceptedQuote($preview, $attributes['quote_reference']);

                $address = $this->createAddress($user, $attributes);
                $addressId = (int) $address->getKey();
                $delivery = $this->checkoutDeliveryService->homeForAddress(
                    $user,
                    $addressId,
                    $checkout,
                    lockForUpdate: true,
                );
            } else {
                $address = $this->ownedAddress($user, (int) $attributes['address_id']);
                $addressId = (int) $address->getKey();
                $delivery = $this->checkoutDeliveryService->homeForAddress(
                    $user,
                    $addressId,
                    $checkout,
                    lockForUpdate: true,
                );
                $this->assertAcceptedQuote($delivery, $attributes['quote_reference']);
            }

            return [$addressId, $method, $delivery];
        }, 3);

        return $this->draftStore->put(
            $user,
            $attributes['contact_name'],
            $attributes['contact_phone'],
            $addressId,
            $method,
            $delivery->snapshot(),
        );
    }

    private function assertAcceptedQuote(CheckoutDeliveryResult $delivery, string $reference): void
    {
        if (! hash_equals($delivery->snapshot()->fingerprint(), $reference)) {
            throw new CheckoutQuoteChangedException(
                'El total o las condiciones de tu compra cambiaron mientras completabas el formulario. Actualizamos la informacion; revisa el resumen y presiona Continuar nuevamente.',
            );
        }
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
