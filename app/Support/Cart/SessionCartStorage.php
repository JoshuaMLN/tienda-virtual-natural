<?php

namespace App\Support\Cart;

class SessionCartStorage implements CartStorageInterface
{
    private const SESSION_KEY = 'shop.cart.items';
    private const WARNINGS_SESSION_KEY = 'shop.cart.warnings';

    public function all(): array
    {
        $items = session()->get(self::SESSION_KEY, []);

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = (int) $quantity;

            if ($productId > 0 && $quantity > 0) {
                $normalized[$productId] = $quantity;
            }
        }

        return $normalized;
    }

    public function set(int $productId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId);

            return;
        }

        $items = $this->all();
        $items[$productId] = $quantity;

        session()->put(self::SESSION_KEY, $items);
    }

    public function remove(int $productId): void
    {
        $items = $this->all();
        unset($items[$productId]);

        session()->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->clearWarnings();
    }

    public function warnings(): array
    {
        $warnings = session()->get(self::WARNINGS_SESSION_KEY, []);

        if (! is_array($warnings)) {
            return [];
        }

        return array_values(array_filter($warnings, fn ($warning) => is_string($warning) && $warning !== ''));
    }

    public function addWarnings(array $warnings): void
    {
        $warnings = array_values(array_filter($warnings, fn ($warning) => is_string($warning) && $warning !== ''));

        if ($warnings === []) {
            return;
        }

        session()->put(self::WARNINGS_SESSION_KEY, array_values(array_unique([
            ...$this->warnings(),
            ...$warnings,
        ])));
    }

    public function clearWarnings(): void
    {
        session()->forget(self::WARNINGS_SESSION_KEY);
    }
}
