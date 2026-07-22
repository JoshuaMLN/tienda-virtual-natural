<?php

namespace App\Support\Checkout;

use App\Enums\CheckoutRevalidationStatus;
use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Enums\TaxAffectation;
use App\Models\Cart as CartModel;
use App\Models\CartItem as CartItemModel;
use App\Models\CustomerAddress;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Orders\OrderCreationService;
use App\Support\Orders\Reservations\StockReservationService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutOrderCreationService
{
    public function __construct(
        private readonly CheckoutRevalidationService $revalidation,
        private readonly CheckoutDraftStore $draftStore,
        private readonly OrderCreationService $orders,
        private readonly StockReservationService $reservations,
        private readonly PendingCheckoutOrderExpirationService $expirations,
    ) {}

    public function confirm(
        User $user,
        string $reviewReference,
        string $idempotencyKey,
        ?string $acceptedProposalReference = null,
    ): CheckoutConfirmationResult {
        $existing = $this->existingOrder($user, $idempotencyKey, $reviewReference);

        if ($existing !== null) {
            $this->cleanConfirmedCartLines($existing);
            $this->clearConfirmedDraft($user, $existing);

            return new CheckoutConfirmationResult(
                null,
                $existing,
                true,
            );
        }

        $pendingOrder = $this->expirations->reconcileFor($user)->pendingOrder;

        if ($pendingOrder !== null) {
            return new CheckoutConfirmationResult(
                order: $pendingOrder,
                blockedByPendingOrder: true,
            );
        }

        $result = $acceptedProposalReference === null
            ? $this->revalidation->revalidate($user, $reviewReference)
            : $this->revalidation->accept($user, $reviewReference, $acceptedProposalReference);

        if ($result->status !== CheckoutRevalidationStatus::Unchanged) {
            return new CheckoutConfirmationResult($result);
        }

        $transactionResult = DB::transaction(function () use ($user, $result, $idempotencyKey): array {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            LegalDocument::query()
                ->where('type', LegalDocumentType::Terms->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $existing = $this->existingOrder($user, $idempotencyKey, $result->reviewReference);

            if ($existing !== null) {
                return [$existing, $result, true, false];
            }

            $existingReview = Order::query()
                ->where('user_id', $user->getKey())
                ->where('checkout_review_reference', $result->reviewReference)
                ->first();

            if ($existingReview !== null) {
                return [$existingReview, $result, true, false];
            }

            $pendingOrder = $this->expirations->reconcileFor($user)->pendingOrder;

            if ($pendingOrder !== null) {
                return [$pendingOrder, $result, false, true];
            }

            $lockedResult = $this->revalidation->revalidate(
                $user,
                $result->reviewReference,
                lockForUpdate: true,
            );

            if ($lockedResult->status !== CheckoutRevalidationStatus::Unchanged) {
                return [null, $lockedResult, false, false];
            }

            $draft = $this->draftStore->get($user);

            if ($draft?->review === null || $draft->deliveryQuote === null || $draft->fiscal === null) {
                throw new CheckoutRevalidationException(
                    'El checkout no tiene una revision completa para crear el pedido.',
                );
            }

            $review = $draft->review;
            $quote = $draft->deliveryQuote;
            $terms = LegalDocument::query()
                ->whereKey($review->termsDocumentId)
                ->lockForUpdate()
                ->first();

            if ($terms === null
                || ! $review->accepts($terms)
                || ! hash_equals($review->termsContentFingerprint, CheckoutReviewSnapshot::contentFingerprint($terms))) {
                throw new CheckoutRevalidationException(
                    'Los terminos aceptados ya no estan disponibles. Vuelve a revisarlos.',
                    409,
                );
            }

            $address = $this->deliveryAddress($user, $draft);
            $products = Product::query()
                ->with('primaryImage')
                ->whereKey(array_column($quote->items, 'product_id'))
                ->orderBy('products.id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count($quote->items)) {
                throw new CheckoutRevalidationException(
                    'Uno de los productos confirmados ya no esta disponible.',
                    409,
                );
            }

            $expiration = CarbonImmutable::now()->addMinutes($this->reservationMinutes());
            $order = $this->orders->create(
                $this->orderAttributes(
                    $user,
                    $draft,
                    $address,
                    $terms,
                    $idempotencyKey,
                    $expiration,
                ),
                $this->itemAttributes($quote, $products->all()),
            );

            foreach ($order->items->sortBy('id') as $item) {
                $this->reservations->reserve($item, $expiration);
            }

            return [$order->fresh(['items', 'statusHistories', 'stockReservations']), $lockedResult, false, false];
        }, 5);

        /** @var Order|null $order */
        [$order, $finalResult, $idempotentReplay, $blockedByPendingOrder] = $transactionResult;

        if ($order === null) {
            return new CheckoutConfirmationResult($finalResult);
        }

        if (! $blockedByPendingOrder) {
            $this->cleanConfirmedCartLines($order);
            $this->clearConfirmedDraft($user, $order);
        }

        return new CheckoutConfirmationResult($finalResult, $order, $idempotentReplay, $blockedByPendingOrder);
    }

    private function existingOrder(User $user, string $idempotencyKey, string $reviewReference): ?Order
    {
        $order = Order::query()
            ->where('user_id', $user->getKey())
            ->where('checkout_idempotency_key', $idempotencyKey)
            ->first();

        if ($order === null) {
            return null;
        }

        if (! is_string($order->checkout_review_reference)
            || ! hash_equals($order->checkout_review_reference, $reviewReference)) {
            throw new CheckoutRevalidationException(
                'La clave de confirmacion ya fue utilizada por otro intento.',
                409,
            );
        }

        return $order->loadMissing(['items', 'statusHistories', 'stockReservations']);
    }

    private function deliveryAddress(User $user, CheckoutDraft $draft): ?CustomerAddress
    {
        if ($draft->deliveryMethod === DeliveryMethod::Pickup) {
            return null;
        }

        if ($draft->addressId === null) {
            throw new DomainException('La entrega a domicilio requiere una direccion.');
        }

        return $user->addresses()
            ->whereKey($draft->addressId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function orderAttributes(
        User $user,
        CheckoutDraft $draft,
        ?CustomerAddress $address,
        LegalDocument $terms,
        string $idempotencyKey,
        CarbonImmutable $expiration,
    ): array {
        $review = $draft->review;
        $quote = $draft->deliveryQuote;
        $fiscal = $draft->fiscal;

        if ($review === null || $quote === null || $fiscal === null) {
            throw new DomainException('El checkout no tiene snapshots completos.');
        }

        return [
            'user_id' => $user->getKey(),
            'pending_payment_owner_id' => $user->getKey(),
            'customer_address_id' => $address?->getKey(),
            'checkout_idempotency_key' => $idempotencyKey,
            'checkout_review_reference' => $review->fingerprint(),
            'customer_name' => $review->contactName,
            'customer_email' => $review->customerEmail,
            'customer_phone' => $review->contactPhone,
            'delivery_method' => $quote->method,
            'delivery_recipient_name' => $address?->recipient_name,
            'delivery_phone' => $address?->phone,
            'delivery_department' => $address?->department,
            'delivery_province' => $address?->province,
            'delivery_district' => $address?->district,
            'delivery_ubigeo' => $address?->ubigeo,
            'delivery_address' => $address?->address_line,
            'delivery_reference' => $address?->reference,
            'pickup_address' => $quote->method === DeliveryMethod::Pickup ? $quote->pickupAddress : null,
            'fiscal_document_type' => $fiscal->documentType,
            'fiscal_identity_document_type' => $fiscal->identityDocumentType,
            'fiscal_identity_document_number' => $fiscal->identityDocumentNumber,
            'fiscal_first_names' => $fiscal->firstNames,
            'fiscal_last_names' => $fiscal->lastNames,
            'fiscal_business_name' => $fiscal->businessName,
            'fiscal_address' => $fiscal->fiscalAddress,
            'fiscal_email' => $fiscal->email,
            'terms_document_id' => $terms->getKey(),
            'terms_document_version' => $review->termsDocumentVersion,
            'terms_content_fingerprint' => $review->termsContentFingerprint,
            'terms_accepted_at' => CarbonImmutable::parse($review->reviewedAt),
            'terms_snapshot' => $this->termsSnapshot($terms),
            'shipping_tax_affectation' => TaxAffectation::Taxed,
            'shipping_tax_rate_bps' => TaxAffectation::Taxed->taxRateBasisPoints(),
            ...$quote->amounts,
            'delivery_business_days_min' => $quote->deliveryBusinessDaysMin,
            'delivery_business_days_max' => $quote->deliveryBusinessDaysMax,
            'reservation_expires_at' => $expiration,
        ];
    }

    /**
     * @param  array<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function itemAttributes(CheckoutDeliverySnapshot $quote, array $products): array
    {
        return array_map(function (array $item) use ($products): array {
            $product = $products[(int) $item['product_id']];

            return [
                ...$item,
                'product_id' => $product->getKey(),
                'product_image' => $product->mainImageSnapshotSource(),
                'product_presentation' => $product->short_description,
                'sale_unit' => 'unidad',
            ];
        }, $quote->items);
    }

    /** @return array<string, mixed> */
    private function termsSnapshot(LegalDocument $terms): array
    {
        return [
            'document_id' => (int) $terms->getKey(),
            'type' => $terms->type->value,
            'version' => (int) $terms->version,
            'title' => $terms->title,
            'body' => $terms->body,
            'settings_snapshot' => $terms->settings_snapshot,
            'settings_fingerprint' => $terms->settings_fingerprint,
            'published_at' => $terms->published_at?->toAtomString(),
        ];
    }

    private function reservationMinutes(): int
    {
        return min(1_440, max(5, Setting::integer(Setting::STOCK_RESERVATION_MINUTES, 15)));
    }

    private function cleanConfirmedCartLines(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedOrder->cart_cleaned_at !== null || $lockedOrder->user_id === null) {
                return;
            }

            $cart = CartModel::query()
                ->where('user_id', $lockedOrder->user_id)
                ->lockForUpdate()
                ->first();
            $confirmed = $lockedOrder->items()
                ->whereNotNull('product_id')
                ->selectRaw('product_id, SUM(quantity) as confirmed_quantity')
                ->groupBy('product_id')
                ->pluck('confirmed_quantity', 'product_id');

            if ($cart !== null && $confirmed->isNotEmpty()) {
                $cartItems = CartItemModel::query()
                    ->where('cart_id', $cart->getKey())
                    ->whereIn('product_id', $confirmed->keys())
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get();

                foreach ($cartItems as $cartItem) {
                    $remaining = $cartItem->quantity - (int) $confirmed->get($cartItem->product_id, 0);

                    if ($remaining > 0) {
                        $cartItem->update(['quantity' => $remaining]);
                    } else {
                        $cartItem->delete();
                    }
                }
            }

            $lockedOrder->markCartCleaned();
        }, 5);
    }

    private function clearConfirmedDraft(User $user, Order $order): void
    {
        if (is_string($order->checkout_review_reference)) {
            $this->draftStore->clearIfReviewMatches($user, $order->checkout_review_reference);
        }
    }
}
