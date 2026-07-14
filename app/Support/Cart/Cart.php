<?php

namespace App\Support\Cart;

use Illuminate\Support\Collection;

class Cart
{
    /**
     * @param  Collection<int, CartItem>  $items
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly Collection $items,
        public readonly array $warnings = [],
    ) {
    }

    public static function empty(array $warnings = []): self
    {
        return new self(collect(), $warnings);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function productCount(): int
    {
        return $this->items->count();
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum(fn (CartItem $item) => $item->quantity);
    }

    public function subtotalCents(): int
    {
        return (int) $this->items->sum(fn (CartItem $item) => $item->subtotalCents());
    }

    public function totalCents(): int
    {
        return $this->subtotalCents();
    }

    public function formattedSubtotal(): string
    {
        return $this->formatMoney($this->subtotalCents());
    }

    public function formattedTotal(): string
    {
        return $this->formatMoney($this->totalCents());
    }

    public function toArray(): array
    {
        return [
            'items' => $this->items->map->toArray()->values()->all(),
            'product_count' => $this->productCount(),
            'total_quantity' => $this->totalQuantity(),
            'subtotal' => $this->subtotalCents() / 100,
            'total' => $this->totalCents() / 100,
            'formatted_subtotal' => $this->formattedSubtotal(),
            'formatted_total' => $this->formattedTotal(),
            'warnings' => $this->warnings,
        ];
    }

    private function formatMoney(int $cents): string
    {
        return 'S/ '.number_format($cents / 100, 2);
    }
}
