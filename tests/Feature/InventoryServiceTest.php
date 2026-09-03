<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Inventory\InsufficientStockException;
use App\Support\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_increase_adds_stock_and_records_movement(): void
    {
        $product = $this->product(stock: 10);

        $movement = $this->service()->increase($product, 5, [
            'reason' => 'Compra a proveedor',
            'notes' => 'Lote inicial',
            'reference' => 'OC-1001',
        ]);

        $this->assertSame(15, $product->stock);
        $this->assertSame(InventoryMovement::TYPE_IN, $movement->type);
        $this->assertSame(5, $movement->quantity);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(15, $movement->stock_after);
        $this->assertSame('Compra a proveedor', $movement->reason);
        $this->assertSame('Lote inicial', $movement->notes);
        $this->assertSame('OC-1001', $movement->reference);
    }

    public function test_decrease_subtracts_stock_and_records_movement(): void
    {
        $product = $this->product(stock: 10);

        $movement = $this->service()->decrease($product, 4, [
            'reason' => 'Salida manual',
        ]);

        $this->assertSame(6, $product->stock);
        $this->assertSame(InventoryMovement::TYPE_OUT, $movement->type);
        $this->assertSame(4, $movement->quantity);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(6, $movement->stock_after);
        $this->assertSame('Salida manual', $movement->reason);
    }

    public function test_adjust_sets_stock_and_records_absolute_difference(): void
    {
        $product = $this->product(stock: 10);

        $movement = $this->service()->adjust($product, 3, [
            'reason' => 'Conteo fisico',
        ]);

        $this->assertSame(3, $product->stock);
        $this->assertSame(InventoryMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertSame(7, $movement->quantity);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(3, $movement->stock_after);
        $this->assertSame('Conteo fisico', $movement->reason);
    }

    public function test_decrease_cannot_leave_negative_stock(): void
    {
        $product = $this->product(stock: 3);

        try {
            $this->service()->decrease($product, 4);
            $this->fail('Expected insufficient stock exception.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(4, $exception->requestedQuantity);
            $this->assertSame(3, $exception->availableStock);
        }

        $this->assertSame(3, $product->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_ensure_available_throws_when_requested_quantity_exceeds_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->service()->ensureAvailable($product, 2);

        $this->expectException(InsufficientStockException::class);

        $this->service()->ensureAvailable($product, 3);
    }

    public function test_quantities_must_be_positive(): void
    {
        $product = $this->product(stock: 10);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->increase($product, 0);
    }

    public function test_adjusted_stock_cannot_be_negative(): void
    {
        $product = $this->product(stock: 10);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->adjust($product, -1);
    }

    private function service(): InventoryService
    {
        return new InventoryService;
    }

    private function product(int $stock): Product
    {
        return Product::query()->create([
            'category_id' => $this->category()->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium-'.uniqid(),
            'sku' => 'VN-OMEGA-'.uniqid(),
            'price' => 79.90,
            'stock' => $stock,
        ]);
    }

    private function category(): Category
    {
        return Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos-'.uniqid(),
            'is_active' => true,
        ]);
    }
}
