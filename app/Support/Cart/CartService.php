<?php

namespace App\Support\Cart;

use App\Models\Product;
use App\Support\Inventory\InventoryService;

class CartService
{
    public function __construct(
        private readonly CartStorageInterface $storage,
        private readonly InventoryService $inventoryService,
    ) {
    }

    public function get(): Cart
    {
        $storedItems = $this->storage->all();

        if ($storedItems === []) {
            return Cart::empty($this->storage->warnings());
        }

        $products = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->active()
            ->whereKey(array_keys($storedItems))
            ->get()
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

    private function resolveActiveProduct(Product|int $product): Product
    {
        $productId = $product instanceof Product ? $product->id : $product;

        $activeProduct = Product::query()
            ->with(['category', 'brand', 'primaryImage'])
            ->active()
            ->whereKey($productId)
            ->first();

        if (! $activeProduct) {
            throw new ProductUnavailableException();
        }

        return $activeProduct;
    }
}
