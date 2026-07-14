<?php

namespace App\Support\Cart;

use App\Models\Product;

class CartItem
{
    public function __construct(
        public readonly Product $product,
        public readonly int $quantity,
        public readonly int $unitPriceCents,
    ) {
    }

    public static function fromProduct(Product $product, int $quantity): self
    {
        return new self(
            product: $product,
            quantity: $quantity,
            unitPriceCents: (int) round((float) $product->price * 100),
        );
    }

    public function subtotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    public function formattedUnitPrice(): string
    {
        return $this->formatMoney($this->unitPriceCents);
    }

    public function formattedSubtotal(): string
    {
        return $this->formatMoney($this->subtotalCents());
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->product->id,
            'slug' => $this->product->slug,
            'name' => $this->product->name,
            'description' => $this->product->short_description,
            'url' => route('shop.product', $this->product->slug),
            'image_url' => $this->product->main_image_url,
            'quantity' => $this->quantity,
            'stock' => (int) $this->product->stock,
            'unit_price' => $this->unitPriceCents / 100,
            'subtotal' => $this->subtotalCents() / 100,
            'formatted_unit_price' => $this->formattedUnitPrice(),
            'formatted_subtotal' => $this->formattedSubtotal(),
            'update_url' => route('shop.cart.items.update', $this->product),
            'remove_url' => route('shop.cart.items.destroy', $this->product),
        ];
    }

    private function formatMoney(int $cents): string
    {
        return 'S/ '.number_format($cents / 100, 2);
    }
}
