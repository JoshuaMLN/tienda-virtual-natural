<?php

namespace App\Support\Cart;

use App\Models\Product;
use App\Support\Inventory\InventoryService;
use App\Support\Money\Money;
use InvalidArgumentException;

class CartService
{
    public function __construct(
        private readonly CartStorageInterface $storage,
        private readonly InventoryService $inventoryService,
    ) {}

    public function get(bool $lockProductsForUpdate = false): Cart
    {
        $storedItems = $this->storage->all();

        if ($storedItems === []) {
            return Cart::empty($this->storage->warnings());
        }

        $priceReferences = $this->storage->priceReferences();
        $productsQuery = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->active()
            ->whereKey(array_keys($storedItems))
            ->orderBy('products.id');

        if ($lockProductsForUpdate) {
            $productsQuery->lockForUpdate();
        }

        $products = $productsQuery->get()
            ->keyBy('id');

        $items = collect();
        $warnings = [];

        foreach ($storedItems as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                $this->storage->remove($productId);
                $warnings[] = 'Un producto fue retirado del carrito porque ya no esta disponible.';

                continue;
            }

            if ($product->stock <= 0) {
                $this->storage->remove($productId);
                $warnings[] = "{$product->name}: solicitaste {$quantity} unidades, pero el producto ya no tiene stock disponible. Lo retiramos de tu carrito.";

                continue;
            }

            if ($quantity > $product->stock) {
                $requestedQuantity = $quantity;
                $quantity = (int) $product->stock;
                $this->storage->set($product->id, $quantity);
                $warnings[] = "{$product->name}: solicitaste {$requestedQuantity} unidades, pero solo hay {$quantity} disponibles. Actualizamos tu carrito a {$quantity} unidades.";
            }

            $priceReference = $priceReferences[$product->id] ?? null;

            if ($priceReference === null) {
                $this->storage->setPriceReference($product->id, (string) $product->price);
            } elseif (! $this->samePrice($priceReference, $product->price)) {
                $warnings[] = sprintf(
                    '%s: su precio cambio de S/ %s a S/ %s. Actualizamos el precio de tu carrito.',
                    $product->name,
                    Money::fromDecimal($priceReference)->formatted(''),
                    Money::fromDecimal($product->price)->formatted('')
                );
                $this->storage->setPriceReference($product->id, (string) $product->price);
            }

            $items->push(CartItem::fromProduct($product, $quantity));
        }

        $this->storage->addWarnings($warnings);

        return new Cart($items, $this->storage->warnings());
    }

    public function count(): int
    {
        return $this->get()->totalQuantity();
    }

    public function add(Product|int $product, int $quantity): Cart
    {
        $product = $this->resolveActiveProduct($product);
        $currentQuantity = $this->storage->all()[$product->id] ?? 0;
        $newQuantity = $currentQuantity + $quantity;

        $this->inventoryService->ensureAvailable($product, $newQuantity);
        $this->storage->set($product->id, $newQuantity);

        return $this->get();
    }

    public function update(Product|int $product, int $quantity): Cart
    {
        $product = $this->resolveActiveProduct($product);

        $this->inventoryService->ensureAvailable($product, $quantity);
        $this->storage->set($product->id, $quantity);

        return $this->get();
    }

    public function remove(Product|int $product): Cart
    {
        $productId = $product instanceof Product ? (int) $product->id : $product;

        $this->storage->remove($productId);

        return $this->get();
    }

    public function clear(): Cart
    {
        $this->storage->clear();

        return Cart::empty();
    }

    public function clearWarnings(): Cart
    {
        $this->storage->clearWarnings();

        return $this->get();
    }

    public function rememberWarning(string $warning): void
    {
        $this->storage->addWarnings([$warning]);
    }

    private function resolveActiveProduct(Product|int $product): Product
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $activeProduct = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->active()
            ->whereKey($productId)
            ->first();

        if (! $activeProduct) {
            throw new ProductUnavailableException;
        }

        return $activeProduct;
    }

    private function samePrice(string|float|int|null $left, string|float|int|null $right): bool
    {
        try {
            return Money::fromDecimal($left ?? '')->cents === Money::fromDecimal($right ?? '')->cents;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
