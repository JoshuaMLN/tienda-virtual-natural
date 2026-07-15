<?php

namespace App\Support\Cart;

use Illuminate\Support\Str;

class SessionCartStorage implements CartStorageInterface
{
    private const SESSION_KEY = 'shop.cart.items';

    private const TOKEN_SESSION_KEY = 'shop.cart.token';

    private const PRICE_REFERENCES_SESSION_KEY = 'shop.cart.price_references';

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
        $this->token();
    }

    public function remove(int $productId): void
    {
        $items = $this->all();
        unset($items[$productId]);
        $priceReferences = $this->priceReferences();
        unset($priceReferences[$productId]);

        if ($items === []) {
            session()->forget([
                self::SESSION_KEY,
                self::TOKEN_SESSION_KEY,
                self::PRICE_REFERENCES_SESSION_KEY,
            ]);

            return;
        }

        session()->put(self::SESSION_KEY, $items);
        session()->put(self::PRICE_REFERENCES_SESSION_KEY, $priceReferences);
    }

    public function clear(): void
    {
        session()->forget([
            self::SESSION_KEY,
            self::TOKEN_SESSION_KEY,
            self::PRICE_REFERENCES_SESSION_KEY,
        ]);
        $this->clearWarnings();
    }

    public function token(): ?string
    {
        if ($this->all() === []) {
            return null;
        }

        $token = session()->get(self::TOKEN_SESSION_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = (string) Str::uuid();
        session()->put(self::TOKEN_SESSION_KEY, $token);

        return $token;
    }

    public function clearItemsAfterMerge(): void
    {
        session()->forget([
            self::SESSION_KEY,
            self::TOKEN_SESSION_KEY,
            self::PRICE_REFERENCES_SESSION_KEY,
        ]);
    }

    public function priceReferences(): array
    {
        $references = session()->get(self::PRICE_REFERENCES_SESSION_KEY, []);

        if (! is_array($references)) {
            return [];
        }

        $normalized = [];

        foreach ($references as $productId => $price) {
            $productId = (int) $productId;

            if ($productId > 0 && is_numeric($price)) {
                $normalized[$productId] = number_format((float) $price, 2, '.', '');
            }
        }

        return $normalized;
    }

    public function setPriceReference(int $productId, string $price): void
    {
        if ($productId <= 0 || ! is_numeric($price)) {
            return;
        }

        $references = $this->priceReferences();
        $references[$productId] = number_format((float) $price, 2, '.', '');

        session()->put(self::PRICE_REFERENCES_SESSION_KEY, $references);
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

    public function removeWarning(string $warning): void
    {
        session()->put(self::WARNINGS_SESSION_KEY, array_values(array_filter(
            $this->warnings(),
            fn (string $storedWarning): bool => $storedWarning !== $warning
        )));
    }
}
