<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Models\User;
use App\Support\Delivery\BusinessDayCalendar;
use App\Support\Delivery\DeliveryService;
use App\Support\Settings\StorefrontSettings;

class CheckoutDeliveryService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly CheckoutReadService $checkoutReadService,
        private readonly StorefrontSettings $settings,
        private readonly BusinessDayCalendar $calendar,
    ) {}

    public function homeForAddress(
        User $user,
        int $addressId,
        CheckoutSummary $checkout,
        bool $lockForUpdate = false,
    ): CheckoutDeliveryResult {
        $query = $user->addresses()->whereKey($addressId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $address = $query->first();

        if ($address === null) {
            throw new CheckoutDeliveryUnavailableException('La direccion seleccionada ya no esta disponible.');
        }

        return $this->homeForUbigeo(
            $address->ubigeo,
            $checkout,
            (int) $address->getKey(),
            $lockForUpdate,
        );
    }

    public function homeForUbigeo(
        string $ubigeo,
        CheckoutSummary $checkout,
        ?int $addressId = null,
        bool $lockForUpdate = false,
    ): CheckoutDeliveryResult {
        $eligibleSubtotalCents = max(
            0,
            $checkout->pricing->productsSubtotalCents - $checkout->pricing->discountCents,
        );
        $deliveryQuote = $this->deliveryService->quoteCents(
            $ubigeo,
            $eligibleSubtotalCents,
            $lockForUpdate,
        );

        if ($deliveryQuote === null) {
            throw new CheckoutDeliveryUnavailableException(
                'La entrega a domicilio no esta disponible para el distrito seleccionado.',
            );
        }

        return new CheckoutDeliveryResult(
            method: DeliveryMethod::HomeDelivery,
            addressId: $addressId,
            deliveryQuote: $deliveryQuote,
            summary: $this->checkoutReadService->withShipping($checkout, $deliveryQuote->shippingFeeCents),
            deliveryBusinessDaysMin: $deliveryQuote->businessDaysMin,
            deliveryBusinessDaysMax: $deliveryQuote->businessDaysMax,
            estimatedDates: $this->calendar->estimate(
                $deliveryQuote->businessDaysMin,
                $deliveryQuote->businessDaysMax,
            ),
            pickupAddress: null,
            pickupHoldDays: $this->settings->pickupHoldDays(),
        );
    }

    public function pickup(CheckoutSummary $checkout): CheckoutDeliveryResult
    {
        if (! $this->deliveryService->pickupAvailable()) {
            throw new CheckoutDeliveryUnavailableException(
                'El recojo en tienda no esta disponible en este momento.',
            );
        }

        return new CheckoutDeliveryResult(
            method: DeliveryMethod::Pickup,
            addressId: null,
            deliveryQuote: null,
            summary: $this->checkoutReadService->withShipping($checkout, 0),
            deliveryBusinessDaysMin: $this->settings->pickupPreparationBusinessDaysMin(),
            deliveryBusinessDaysMax: $this->settings->pickupPreparationBusinessDaysMax(),
            estimatedDates: $this->calendar->estimate(
                $this->settings->pickupPreparationBusinessDaysMin(),
                $this->settings->pickupPreparationBusinessDaysMax(),
            ),
            pickupAddress: $this->settings->pickupAddress(),
            pickupHoldDays: $this->settings->pickupHoldDays(),
        );
    }

    /**
     * @return array{
     *     selected_method: string|null,
     *     pickup_available: bool,
     *     pickup_address: string|null,
     *     delivery_window_label: string,
     *     pickup_estimated_date_label: string|null,
     *     pickup_availability_label: string|null,
     *     pickup_hold_days: int,
     *     whatsapp_url: string|null,
     *     whatsapp_display: string,
     *     unavailable_message: string|null,
     *     quote: array<string, mixed>|null,
     *     base_summary: array{amounts: array<string, int|string>}
     * }
     */
    public function initialState(
        User $user,
        CheckoutSummary $checkout,
        ?DeliveryMethod $requestedMethod,
        ?int $addressId,
    ): array {
        $selectedMethod = $requestedMethod;
        $result = null;
        $unavailableMessage = null;

        if ($selectedMethod === null && $addressId !== null) {
            try {
                $result = $this->homeForAddress($user, $addressId, $checkout);
                $selectedMethod = DeliveryMethod::HomeDelivery;
            } catch (CheckoutDeliveryUnavailableException) {
                // An uncovered default address must not select another modality silently.
            }
        } elseif ($selectedMethod === DeliveryMethod::HomeDelivery) {
            if ($addressId === null) {
                $unavailableMessage = 'Selecciona una direccion con cobertura para cotizar la entrega.';
            } else {
                try {
                    $result = $this->homeForAddress($user, $addressId, $checkout);
                } catch (CheckoutDeliveryUnavailableException $exception) {
                    $unavailableMessage = $exception->getMessage();
                }
            }
        } elseif ($selectedMethod === DeliveryMethod::Pickup) {
            try {
                $result = $this->pickup($checkout);
            } catch (CheckoutDeliveryUnavailableException $exception) {
                $selectedMethod = null;
                $unavailableMessage = $exception->getMessage();
            }
        }

        return [
            'selected_method' => $selectedMethod?->value,
            ...$this->configuration(),
            'unavailable_message' => $unavailableMessage,
            'quote' => $result?->toArray(),
            'base_summary' => [
                'amounts' => $checkout->amountsToArray(),
            ],
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public function configuration(): array
    {
        $pickupAvailable = $this->deliveryService->pickupAvailable();
        $pickupEstimatedDates = $pickupAvailable
            ? $this->calendar->estimate(
                $this->settings->pickupPreparationBusinessDaysMin(),
                $this->settings->pickupPreparationBusinessDaysMax(),
            )
            : null;

        return [
            'pickup_available' => $pickupAvailable,
            'pickup_address' => $pickupAvailable
                ? $this->settings->pickupAddress()
                : null,
            'delivery_window_label' => $pickupEstimatedDates?->label() ?? 'Por confirmar',
            'pickup_estimated_date_label' => $pickupEstimatedDates?->label(),
            'pickup_availability_label' => $pickupEstimatedDates?->availabilityLabel(),
            'pickup_hold_days' => $this->settings->pickupHoldDays(),
            'whatsapp_url' => $this->settings->whatsappUrl(),
            'whatsapp_display' => $this->settings->whatsappDisplay(),
        ];
    }
}
