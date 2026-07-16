<?php

namespace App\Support\Cart;

use App\Models\Cart as CartModel;
use App\Models\Product;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\DB;
use LogicException;

class DatabaseCartStorage implements CartStorageInterface
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly CartMergeCoordinator $mergeCoordinator,
        private readonly SessionCartStorage $sessionStorage,
    ) {}

    public function all(): array
    {
        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);
        $cart = $user->cart()->first();

        if (! $cart) {
            return [];
        }

        $items = $cart->items()->get();

        return $items
            ->filter(fn ($item): bool => $item->quantity > 0)
            ->mapWithKeys(fn ($item): array => [
                (int) $item->product_id => (int) $item->quantity,
            ])
            ->all();
    }

    public function set(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId);

            return;
        }

        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);
        $product = Product::query()->findOrFail($productId);

        DB::transaction(function () use ($user, $product, $quantity): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $cart = CartModel::query()->firstOrCreate([
                'user_id' => $lockedUser->getKey(),
            ]);

            $item = $cart->items()->firstOrNew([
                'product_id' => $product->getKey(),
            ]);
            $item->quantity = $quantity;

            if (! $item->exists) {
                $item->price_reference = $product->price;
            }

            $item->save();
        });
    }

    public function remove(int $productId): void
    {
        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);

        $user->cart()->first()?->items()
            ->where('product_id', $productId)
            ->delete();
    }

    public function clear(): void
    {
        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);

        $user->cart()->first()?->items()->delete();
        $this->mergeCoordinator->discardPendingGuestCart();
    }

    public function priceReferences(): array
    {
        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);
        $cart = $user->cart()->first();

        if (! $cart) {
            return [];
        }

        return $cart->items()
            ->pluck('price_reference', 'product_id')
            ->mapWithKeys(fn ($price, $productId): array => [
                (int) $productId => Money::fromDecimal($price)->decimal(),
            ])
            ->all();
    }

    public function setPriceReference(int $productId, string $price): void
    {
        if (! is_numeric($price)) {
            return;
        }

        $user = $this->user();
        $this->mergeCoordinator->ensureMerged($user);

        $user->cart()->first()?->items()
            ->where('product_id', $productId)
            ->update([
                'price_reference' => Money::fromDecimal($price)->decimal(),
            ]);
    }

    public function warnings(): array
    {
        return $this->sessionStorage->warnings();
    }

    public function addWarnings(array $warnings): void
    {
        $this->sessionStorage->addWarnings($warnings);
    }

    public function clearWarnings(): void
    {
        $this->sessionStorage->clearWarnings();
    }

    private function user(): User
    {
        $user = $this->auth->guard('web')->user();

        if (! $user instanceof User) {
            throw new LogicException('DatabaseCartStorage requiere un usuario autenticado.');
        }

        return $user;
    }
}
