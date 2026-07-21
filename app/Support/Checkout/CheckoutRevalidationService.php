<?php

namespace App\Support\Checkout;

use App\Enums\CheckoutChangeType;
use App\Enums\CheckoutRevalidationStatus;
use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\User;
use App\Support\Cart\Cart;
use App\Support\Cart\CartItem;
use App\Support\Legal\LegalDocumentService;

class CheckoutRevalidationService
{
    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutDeliveryService $deliveryService,
        private readonly CheckoutDraftStore $draftStore,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    public function revalidate(
        User $user,
        ?string $expectedReviewReference = null,
    ): CheckoutRevalidationResult {
        $draft = $this->draftStore->get($user);

        if ($draft?->review === null || $draft->deliveryQuote === null || $draft->deliveryMethod === null) {
            throw new CheckoutRevalidationException(
                'El checkout no tiene una revision vigente. Revisa nuevamente tus datos antes de continuar.',
            );
        }

        if (
            $expectedReviewReference !== null
            && ! hash_equals($draft->review->fingerprint(), $expectedReviewReference)
        ) {
            throw new CheckoutRevalidationException(
                'Esta revision del checkout ya no esta vigente. Recarga la pagina para continuar con el estado mas reciente.',
                409,
            );
        }

        $previous = $draft->deliveryQuote;
        $currentCart = $this->checkoutReadService->currentCart();
        [$scopedCart, $preservedCartItems] = $this->scopeCart($currentCart, $previous->items);
        $currentCheckout = $this->checkoutReadService->summarize($scopedCart);
        $current = null;
        $deliveryUnavailable = null;

        if ($currentCheckout !== null) {
            try {
                $current = match ($draft->deliveryMethod) {
                    DeliveryMethod::HomeDelivery => $draft->addressId !== null
                        ? $this->deliveryService->homeForAddress($user, $draft->addressId, $currentCheckout)->snapshot()
                        : null,
                    DeliveryMethod::Pickup => $this->deliveryService->pickup($currentCheckout)->snapshot(),
                };

                if ($current === null) {
                    $deliveryUnavailable = 'La direccion de entrega ya no esta disponible.';
                }
            } catch (CheckoutDeliveryUnavailableException $exception) {
                $deliveryUnavailable = $exception->getMessage();
            }
        }

        $currentItems = $current?->items ?? $currentCheckout?->itemSnapshots() ?? [];
        $changes = $this->itemChanges($previous->items, $currentItems);

        if ($deliveryUnavailable !== null) {
            $changes[] = new CheckoutRevalidationChange(
                CheckoutChangeType::DeliveryUnavailable,
                [
                    'available' => true,
                    'method' => $previous->method->value,
                    'address_id' => $previous->addressId,
                    'ubigeo' => $previous->ubigeo,
                ],
                [
                    'available' => false,
                    'reason' => $deliveryUnavailable,
                ],
            );
        } elseif ($current !== null) {
            $changes = [
                ...$changes,
                ...$this->deliveryChanges($previous, $current),
                ...$this->amountChanges($previous->amounts, $current->amounts),
            ];
        }

        $terms = $this->legalDocuments->active(LegalDocumentType::Terms);
        $requiresTermsAcceptance = $terms === null || ! $draft->review->accepts($terms);

        if ($requiresTermsAcceptance) {
            $changes[] = $this->termsChange($draft->review, $terms);
        }

        $blocked = $current === null || $requiresTermsAcceptance;
        $status = $blocked
            ? CheckoutRevalidationStatus::Blocked
            : ($changes === [] ? CheckoutRevalidationStatus::Unchanged : CheckoutRevalidationStatus::Changed);

        return new CheckoutRevalidationResult(
            status: $status,
            reviewReference: $draft->review->fingerprint(),
            previous: $previous,
            current: $current,
            changes: $changes,
            preservedCartItems: $preservedCartItems,
            requiresTermsAcceptance: $requiresTermsAcceptance,
        );
    }

    public function accept(
        User $user,
        string $expectedReviewReference,
        string $acceptedProposalReference,
    ): CheckoutRevalidationResult {
        $result = $this->revalidate($user, $expectedReviewReference);

        if (! $result->requiresConfirmation() || $result->current === null) {
            return $result;
        }

        if (! hash_equals($result->current->fingerprint(), $acceptedProposalReference)) {
            return $result;
        }

        $draft = $this->draftStore->get($user);
        $terms = $this->legalDocuments->active(LegalDocumentType::Terms);

        if (
            $draft?->review === null
            || $draft->fiscal === null
            || $terms === null
            || ! $draft->review->accepts($terms)
        ) {
            return $this->revalidate($user, $expectedReviewReference);
        }

        $review = CheckoutReviewSnapshot::create(
            userId: (int) $user->getKey(),
            contactName: $draft->contactName,
            customerEmail: (string) $user->email,
            contactPhone: $draft->contactPhone,
            deliveryQuote: $result->current,
            fiscal: $draft->fiscal,
            terms: $terms,
        );

        $updated = $this->draftStore->putRevalidatedReview(
            $user,
            $result->current,
            $review,
            $expectedReviewReference,
        );

        if ($updated === null) {
            throw new CheckoutRevalidationException(
                'El checkout cambio mientras confirmabas. Vuelve a revisar la propuesta vigente.',
                409,
            );
        }

        return $this->revalidate($user, $review->fingerprint());
    }

    /**
     * @param  list<array<string, int|string>>  $reviewedItems
     * @return array{Cart, list<array{product_id: int, product_name: string, quantity: int}>}
     */
    private function scopeCart(Cart $cart, array $reviewedItems): array
    {
        $reviewedQuantities = [];

        foreach ($reviewedItems as $item) {
            $reviewedQuantities[(int) $item['product_id']] = (int) $item['quantity'];
        }

        $scopedItems = collect();
        $preservedItems = [];

        foreach ($cart->items->sortBy(fn (CartItem $item): int => (int) $item->product->getKey()) as $item) {
            $productId = (int) $item->product->getKey();
            $reviewedQuantity = $reviewedQuantities[$productId] ?? null;

            if ($reviewedQuantity === null) {
                $preservedItems[] = [
                    'product_id' => $productId,
                    'product_name' => (string) $item->product->name,
                    'quantity' => $item->quantity,
                ];

                continue;
            }

            $proposedQuantity = min($reviewedQuantity, $item->quantity);

            if ($proposedQuantity > 0) {
                $scopedItems->push(new CartItem(
                    product: $item->product,
                    quantity: $proposedQuantity,
                    unitPriceCents: $item->unitPriceCents,
                ));
            }

            if ($item->quantity > $proposedQuantity) {
                $preservedItems[] = [
                    'product_id' => $productId,
                    'product_name' => (string) $item->product->name,
                    'quantity' => $item->quantity - $proposedQuantity,
                ];
            }
        }

        return [new Cart($scopedItems, $cart->warnings), $preservedItems];
    }

    /**
     * @param  list<array<string, int|string>>  $previousItems
     * @param  list<array<string, int|string>>  $currentItems
     * @return list<CheckoutRevalidationChange>
     */
    private function itemChanges(array $previousItems, array $currentItems): array
    {
        $currentByProduct = [];

        foreach ($currentItems as $item) {
            $currentByProduct[(int) $item['product_id']] = $item;
        }

        $changes = [];

        foreach ($previousItems as $previous) {
            $productId = (int) $previous['product_id'];
            $current = $currentByProduct[$productId] ?? null;

            if ($current === null) {
                $changes[] = new CheckoutRevalidationChange(
                    CheckoutChangeType::ProductRemoved,
                    (int) $previous['quantity'],
                    0,
                    $productId,
                    (string) $previous['product_name'],
                );

                continue;
            }

            if ((int) $previous['quantity'] !== (int) $current['quantity']) {
                $changes[] = new CheckoutRevalidationChange(
                    CheckoutChangeType::ProductQuantityReduced,
                    (int) $previous['quantity'],
                    (int) $current['quantity'],
                    $productId,
                    (string) $current['product_name'],
                );
            }

            if ((int) $previous['unit_price_cents'] !== (int) $current['unit_price_cents']) {
                $changes[] = new CheckoutRevalidationChange(
                    CheckoutChangeType::ProductPriceChanged,
                    (int) $previous['unit_price_cents'],
                    (int) $current['unit_price_cents'],
                    $productId,
                    (string) $current['product_name'],
                );
            }

            $previousTax = [
                'tax_affectation' => (string) $previous['tax_affectation'],
                'tax_rate_bps' => (int) $previous['tax_rate_bps'],
            ];
            $currentTax = [
                'tax_affectation' => (string) $current['tax_affectation'],
                'tax_rate_bps' => (int) $current['tax_rate_bps'],
            ];

            if ($previousTax !== $currentTax) {
                $changes[] = new CheckoutRevalidationChange(
                    CheckoutChangeType::ProductTaxChanged,
                    $previousTax,
                    $currentTax,
                    $productId,
                    (string) $current['product_name'],
                );
            }

            $previousIdentity = [
                'sku' => (string) $previous['product_sku'],
                'name' => (string) $previous['product_name'],
            ];
            $currentIdentity = [
                'sku' => (string) $current['product_sku'],
                'name' => (string) $current['product_name'],
            ];

            if ($previousIdentity !== $currentIdentity) {
                $changes[] = new CheckoutRevalidationChange(
                    CheckoutChangeType::ProductIdentityChanged,
                    $previousIdentity,
                    $currentIdentity,
                    $productId,
                    (string) $current['product_name'],
                );
            }
        }

        return $changes;
    }

    /** @return list<CheckoutRevalidationChange> */
    private function deliveryChanges(
        CheckoutDeliverySnapshot $previous,
        CheckoutDeliverySnapshot $current,
    ): array {
        $changes = [];

        $this->addChange(
            $changes,
            CheckoutChangeType::DeliveryBaseFeeChanged,
            $previous->baseFeeCents,
            $current->baseFeeCents,
        );
        $this->addChange(
            $changes,
            CheckoutChangeType::ShippingFeeChanged,
            $previous->amounts['shipping_fee_cents'],
            $current->amounts['shipping_fee_cents'],
        );
        $this->addChange(
            $changes,
            CheckoutChangeType::FreeShippingChanged,
            $previous->hasFreeShipping,
            $current->hasFreeShipping,
        );

        $this->addChange(
            $changes,
            CheckoutChangeType::DeliveryEstimateChanged,
            [
                'business_days_min' => $previous->deliveryBusinessDaysMin,
                'business_days_max' => $previous->deliveryBusinessDaysMax,
                'estimated_from' => $previous->estimatedFrom,
                'estimated_to' => $previous->estimatedTo,
            ],
            [
                'business_days_min' => $current->deliveryBusinessDaysMin,
                'business_days_max' => $current->deliveryBusinessDaysMax,
                'estimated_from' => $current->estimatedFrom,
                'estimated_to' => $current->estimatedTo,
            ],
        );

        $this->addChange(
            $changes,
            CheckoutChangeType::PickupDetailsChanged,
            [
                'pickup_address' => $previous->pickupAddress,
                'pickup_hold_days' => $previous->pickupHoldDays,
            ],
            [
                'pickup_address' => $current->pickupAddress,
                'pickup_hold_days' => $current->pickupHoldDays,
            ],
        );

        $previousIdentity = [
            'method' => $previous->method->value,
            'address_id' => $previous->addressId,
            'ubigeo' => $previous->ubigeo,
        ];
        $currentIdentity = [
            'method' => $current->method->value,
            'address_id' => $current->addressId,
            'ubigeo' => $current->ubigeo,
        ];
        $this->addChange(
            $changes,
            CheckoutChangeType::DeliveryDetailsChanged,
            $previousIdentity,
            $currentIdentity,
        );

        return $changes;
    }

    /**
     * @param  array<string, int>  $previous
     * @param  array<string, int>  $current
     * @return list<CheckoutRevalidationChange>
     */
    private function amountChanges(array $previous, array $current): array
    {
        $changes = [];
        $fields = [
            'products_subtotal_cents' => CheckoutChangeType::ProductsSubtotalChanged,
            'discount_cents' => CheckoutChangeType::DiscountChanged,
            'shipping_net_value_cents' => CheckoutChangeType::ShippingNetValueChanged,
            'shipping_tax_cents' => CheckoutChangeType::ShippingTaxChanged,
            'taxable_value_cents' => CheckoutChangeType::TaxableValueChanged,
            'exempt_value_cents' => CheckoutChangeType::ExemptValueChanged,
            'unaffected_value_cents' => CheckoutChangeType::UnaffectedValueChanged,
            'net_value_cents' => CheckoutChangeType::NetValueChanged,
            'tax_cents' => CheckoutChangeType::TaxChanged,
            'total_cents' => CheckoutChangeType::TotalChanged,
        ];

        foreach ($fields as $field => $type) {
            $this->addChange($changes, $type, $previous[$field], $current[$field]);
        }

        return $changes;
    }

    private function termsChange(
        CheckoutReviewSnapshot $review,
        ?LegalDocument $terms,
    ): CheckoutRevalidationChange {
        return new CheckoutRevalidationChange(
            CheckoutChangeType::TermsChanged,
            [
                'document_id' => $review->termsDocumentId,
                'version' => $review->termsDocumentVersion,
                'fingerprint' => $review->termsContentFingerprint,
            ],
            $terms === null ? null : [
                'document_id' => (int) $terms->getKey(),
                'version' => (int) $terms->version,
            ],
        );
    }

    /** @param list<CheckoutRevalidationChange> $changes */
    private function addChange(
        array &$changes,
        CheckoutChangeType $type,
        mixed $previous,
        mixed $current,
    ): void {
        if ($previous === $current) {
            return;
        }

        $changes[] = new CheckoutRevalidationChange($type, $previous, $current);
    }
}
