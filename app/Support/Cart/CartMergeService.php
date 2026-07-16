<?php

namespace App\Support\Cart;

use App\Models\Cart as CartModel;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CartMergeService
{
    public function __construct(
        private readonly SessionCartStorage $sessionStorage,
    ) {}

    public function merge(User $user): void
    {
        $guestItems = $this->sessionStorage->all();
        $guestPriceReferences = $this->sessionStorage->priceReferences();
        $sessionToken = $this->sessionStorage->token();

        if ($guestItems === [] || $sessionToken === null) {
            return;
        }

        $warnings = DB::transaction(function () use (
            $user,
            $guestItems,
            $guestPriceReferences,
            $sessionToken
        ): array {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $cart = CartModel::query()->firstOrCreate([
                'user_id' => $lockedUser->getKey(),
            ]);
            $cart = CartModel::query()
                ->whereKey($cart->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($cart->last_merged_session_token === $sessionToken) {
                return [];
            }

            $productIds = array_keys($guestItems);
            $products = Product::query()
                ->whereKey($productIds)
                ->get()
                ->keyBy('id');
            $visibleProductIds = Product::query()
                ->active()
                ->whereKey($productIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $visibleProductIds = array_fill_keys($visibleProductIds, true);
            $storedItems = $cart->items()
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            $warnings = [];

            foreach ($guestItems as $productId => $guestQuantity) {
                $product = $products->get($productId);
                $storedItem = $storedItems->get($productId);
                $storedQuantity = $storedItem?->quantity ?? 0;
                $requestedQuantity = $storedQuantity + $guestQuantity;

                if (! $product || ! isset($visibleProductIds[$productId])) {
                    $storedItem?->delete();
                    $warnings[] = $product
                        ? "{$product->name}: lo retiramos de tu carrito porque ya no esta disponible."
                        : 'Un producto fue retirado del carrito porque ya no esta disponible.';

                    continue;
                }

                if ($product->stock <= 0) {
                    $storedItem?->delete();
                    $warnings[] = "{$product->name}: solicitaste {$requestedQuantity} unidades, pero el producto ya no tiene stock disponible. Lo retiramos de tu carrito.";

                    continue;
                }

                $quantity = min($requestedQuantity, (int) $product->stock);

                if ($requestedQuantity > $quantity) {
                    $warnings[] = "{$product->name}: solicitaste {$requestedQuantity} unidades entre tus carritos, pero solo hay {$quantity} disponibles. Actualizamos tu carrito a {$quantity} unidades.";
                }

                $guestPriceReference = $guestPriceReferences[$productId] ?? null;
                $storedPriceReference = $storedItem?->price_reference;
                $previousPrice = $guestPriceReference !== null
                    && ! $this->samePrice($guestPriceReference, $product->price)
                        ? $guestPriceReference
                        : $storedPriceReference;

                if (
                    $previousPrice !== null
                    && ! $this->samePrice($previousPrice, $product->price)
                ) {
                    $warnings[] = $this->priceChangedWarning(
                        $product->name,
                        $previousPrice,
                        $product->price
                    );
                }

                CartItem::query()->updateOrCreate(
                    [
                        'cart_id' => $cart->getKey(),
                        'product_id' => $productId,
                    ],
                    [
                        'quantity' => $quantity,
                        'price_reference' => $product->price,
                    ]
                );
            }

            $cart->update(['last_merged_session_token' => $sessionToken]);

            return $warnings;
        });

        $this->sessionStorage->clearItemsAfterMerge();
        $this->sessionStorage->addWarnings($warnings);
    }

    private function samePrice(string|float|int|null $left, string|float|int|null $right): bool
    {
        try {
            return Money::fromDecimal($left ?? '')->cents === Money::fromDecimal($right ?? '')->cents;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function priceChangedWarning(
        string $productName,
        string|float|int $previousPrice,
        string|float|int $currentPrice
    ): string {
        return sprintf(
            '%s: su precio cambio de S/ %s a S/ %s. Actualizamos el precio de tu carrito.',
            $productName,
            Money::fromDecimal($previousPrice)->formatted(''),
            Money::fromDecimal($currentPrice)->formatted('')
        );
    }
}
