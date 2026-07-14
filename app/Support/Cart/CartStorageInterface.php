<?php

namespace App\Support\Cart;

interface CartStorageInterface
{
    /**
     * @return array<int, int>
     */
    public function all(): array;

    public function set(int $productId, int $quantity): void;

    public function remove(int $productId): void;

    public function clear(): void;

    /**
     * @return array<int, string>
     */
    public function warnings(): array;

    /**
     * @param  array<int, string>  $warnings
     */
    public function addWarnings(array $warnings): void;

    public function clearWarnings(): void;
}
