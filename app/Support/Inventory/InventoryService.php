<?php

namespace App\Support\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function increase(Product $product, int $quantity, array $data = []): InventoryMovement
    {
        $this->ensurePositiveQuantity($quantity);

        return $this->recordMovement($product, InventoryMovement::TYPE_IN, $quantity, $data);
    }

    public function decrease(Product $product, int $quantity, array $data = []): InventoryMovement
    {
        $this->ensurePositiveQuantity($quantity);

        return $this->recordMovement($product, InventoryMovement::TYPE_OUT, $quantity, $data);
    }

    public function adjust(Product $product, int $newStock, array $data = []): InventoryMovement
    {
        if ($newStock < 0) {
            throw new InvalidArgumentException('El stock ajustado no puede ser negativo.');
        }

        return DB::transaction(function () use ($product, $newStock, $data) {
            $lockedProduct = $this->lockProduct($product);
            $stockBefore = (int) $lockedProduct->stock;
            $quantity = abs($newStock - $stockBefore);

            $lockedProduct->forceFill(['stock' => $newStock])->save();
            $product->refresh();

            return $this->createMovement(
                $lockedProduct,
                InventoryMovement::TYPE_ADJUSTMENT,
                $quantity,
                $stockBefore,
                $newStock,
                $data,
            );
        });
    }

    public function ensureAvailable(Product $product, int $quantity): void
    {
        $this->ensurePositiveQuantity($quantity);

        $availableStock = (int) $product->stock;

        if ($availableStock < $quantity) {
            throw new InsufficientStockException($quantity, $availableStock);
        }
    }

    private function recordMovement(Product $product, string $type, int $quantity, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($product, $type, $quantity, $data) {
            $lockedProduct = $this->lockProduct($product);
            $stockBefore = (int) $lockedProduct->stock;

            if ($type === InventoryMovement::TYPE_OUT && $stockBefore < $quantity) {
                throw new InsufficientStockException($quantity, $stockBefore);
            }

            $stockAfter = $type === InventoryMovement::TYPE_IN
                ? $stockBefore + $quantity
                : $stockBefore - $quantity;

            $lockedProduct->forceFill(['stock' => $stockAfter])->save();
            $product->refresh();

            return $this->createMovement($lockedProduct, $type, $quantity, $stockBefore, $stockAfter, $data);
        });
    }

    private function createMovement(
        Product $product,
        string $type,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        array $data,
    ): InventoryMovement {
        return $product->inventoryMovements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reason' => $data['reason'] ?? $this->defaultReason($type),
            'notes' => $data['notes'] ?? null,
            'reference' => $data['reference'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
    }

    private function lockProduct(Product $product): Product
    {
        return Product::query()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensurePositiveQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }
    }

    private function defaultReason(string $type): string
    {
        return match ($type) {
            InventoryMovement::TYPE_IN => 'Ingreso de inventario',
            InventoryMovement::TYPE_OUT => 'Salida de inventario',
            InventoryMovement::TYPE_ADJUSTMENT => 'Ajuste de inventario',
            default => 'Movimiento de inventario',
        };
    }
}
