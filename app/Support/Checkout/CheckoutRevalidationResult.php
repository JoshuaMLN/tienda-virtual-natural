<?php

namespace App\Support\Checkout;

use App\Enums\CheckoutRevalidationStatus;

final readonly class CheckoutRevalidationResult
{
    /**
     * @param  list<CheckoutRevalidationChange>  $changes
     * @param  list<array{product_id: int, product_name: string, quantity: int}>  $preservedCartItems
     */
    public function __construct(
        public CheckoutRevalidationStatus $status,
        public string $reviewReference,
        public CheckoutDeliverySnapshot $previous,
        public ?CheckoutDeliverySnapshot $current,
        public array $changes,
        public array $preservedCartItems,
        public bool $requiresTermsAcceptance,
    ) {}

    public function canContinue(): bool
    {
        return $this->status !== CheckoutRevalidationStatus::Blocked;
    }

    public function requiresConfirmation(): bool
    {
        return $this->status === CheckoutRevalidationStatus::Changed;
    }

    public function hasChanges(): bool
    {
        return $this->changes !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'can_continue' => $this->canContinue(),
            'requires_confirmation' => $this->requiresConfirmation(),
            'requires_terms_acceptance' => $this->requiresTermsAcceptance,
            'review_reference' => $this->reviewReference,
            'proposal_reference' => $this->current?->fingerprint(),
            'previous' => $this->previous->toArray(),
            'current' => $this->current?->toArray(),
            'changes' => array_map(
                fn (CheckoutRevalidationChange $change): array => $change->toArray(),
                $this->changes,
            ),
            'preserved_cart_items' => $this->preservedCartItems,
        ];
    }
}
