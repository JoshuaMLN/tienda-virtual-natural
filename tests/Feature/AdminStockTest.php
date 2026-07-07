<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_stock_list_with_real_data_and_summary(): void
    {
        [$category, $brand] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 45,
            'low_stock_threshold' => 10,
        ]);

        $this->product([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-250',
            'stock' => 5,
            'low_stock_threshold' => 10,
        ]);

        $this->product([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto agotado',
            'slug' => 'producto-agotado',
            'sku' => 'VN-AGOTADO',
            'stock' => 0,
            'low_stock_threshold' => 5,
        ]);

        $this->get(route('admin.stock.index'))
            ->assertOk()
            ->assertSee('Stock')
            ->assertSee('Productos totales')
            ->assertSee('Stock total')
            ->assertSee('Omega 3 Premium')
            ->assertSee('VN-OMEGA-120')
            ->assertSee('Suplementos')
            ->assertSee('Good Nature')
            ->assertSee('Optimo')
            ->assertSee('Bajo stock')
            ->assertSee('Sin stock')
            ->assertSee('Editar alerta de stock minimo')
            ->assertSee('Con 0 no se marca bajo stock.')
            ->assertSee(route('admin.stock.threshold.update', $product), false)
            ->assertSee(route('admin.stock.movements.store', $product), false)
            ->assertSee('data-bs-target="#stockThresholdModal-'.$product->id.'"', false)
            ->assertSee('data-bs-target="#stockMovementModal-'.$product->id.'"', false)
            ->assertSee('Registrar movimiento')
            ->assertSee('Ingreso')
            ->assertSee('Salida')
            ->assertSee('Ajuste')
            ->assertSee('Stock minimo de alerta <span class="required-mark"', false)
            ->assertSee('Tipo de movimiento <span class="required-mark"', false)
            ->assertSee('Cantidad <span class="required-mark"', false)
            ->assertSee('Stock final <span class="required-mark"', false)
            ->assertSee('Motivo <span class="required-mark"', false)
            ->assertSee('Manual y opcional: orden de compra, guia, factura o conteo.')
            ->assertDontSee(route('admin.products.edit', $product), false);
    }

    public function test_admin_can_filter_stock_products(): void
    {
        [$category, $brand] = $this->catalogRelations();
        $otherCategory = Category::query()->create([
            'name' => 'Superfoods',
            'slug' => 'superfoods',
            'is_active' => true,
        ]);
        $otherBrand = Brand::query()->create([
            'name' => 'Andean Naturals',
            'slug' => 'andean-naturals',
            'is_active' => true,
        ]);

        $this->product([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 4,
            'low_stock_threshold' => 10,
        ]);

        $this->product([
            'category_id' => $otherCategory->id,
            'brand_id' => $otherBrand->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-250',
            'stock' => 40,
            'low_stock_threshold' => 10,
        ]);

        $this->get(route('admin.stock.index', [
            'q' => 'omega',
            'categoria' => $category->id,
            'marca' => $brand->id,
            'estado_stock' => 'bajo-stock',
        ]))
            ->assertOk()
            ->assertSee('Omega 3 Premium')
            ->assertDontSee('Maca Negra');
    }

    public function test_admin_stock_list_is_ordered_by_product_name(): void
    {
        [$category] = $this->catalogRelations();

        $this->product([
            'category_id' => $category->id,
            'name' => 'Zinc 25 mg',
            'slug' => 'zinc-25-mg',
            'sku' => 'VN-ZINC-25',
            'stock' => 0,
        ]);

        $this->product([
            'category_id' => $category->id,
            'name' => 'Ashwagandha',
            'slug' => 'ashwagandha',
            'sku' => 'VN-ASH-60',
            'stock' => 12,
        ]);

        $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 3,
        ]);

        $this->get(route('admin.stock.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Ashwagandha',
                'Omega 3 Premium',
                'Zinc 25 mg',
            ]);
    }

    public function test_stock_pagination_keeps_current_filters(): void
    {
        [$category] = $this->catalogRelations();

        for ($index = 1; $index <= 16; $index++) {
            $this->product([
                'category_id' => $category->id,
                'name' => "Producto {$index}",
                'slug' => "producto-{$index}",
                'sku' => "VN-PROD-{$index}",
                'stock' => 2,
                'low_stock_threshold' => 5,
            ]);
        }

        $this->get(route('admin.stock.index', [
            'q' => 'producto',
            'estado_stock' => 'bajo-stock',
        ]))
            ->assertOk()
            ->assertSee('q=producto', false)
            ->assertSee('estado_stock=bajo-stock', false)
            ->assertSee('page=2', false);
    }

    public function test_admin_can_view_product_stock_movements(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
            'low_stock_threshold' => 5,
        ]);

        $product->inventoryMovements()->create([
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'reason' => 'Compra a proveedor',
            'reference' => 'OC-1001',
        ]);

        $product->inventoryMovements()->create([
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 2,
            'stock_before' => 10,
            'stock_after' => 8,
            'reason' => 'Salida manual',
        ]);

        $this->get(route('admin.stock.movements.index', $product))
            ->assertOk()
            ->assertSee('Historial de stock')
            ->assertSee('Omega 3 Premium')
            ->assertSee('Compra a proveedor')
            ->assertSee('OC-1001')
            ->assertSee('Salida manual')
            ->assertSee('Stock actual');
    }

    public function test_admin_can_register_stock_increase_from_stock_page(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
        ]);

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.movements.store', $product), [
                'movement_product_id' => $product->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => '7',
                'reason' => 'Compra a proveedor',
                'reference' => 'OC-1001',
                'notes' => 'Lote nuevo',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHas('success');

        $this->assertSame(15, $product->refresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => 7,
            'stock_before' => 8,
            'stock_after' => 15,
            'reason' => 'Compra a proveedor',
            'reference' => 'OC-1001',
            'notes' => 'Lote nuevo',
        ]);
    }

    public function test_admin_can_register_stock_decrease_from_stock_page(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
        ]);

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.movements.store', $product), [
                'movement_product_id' => $product->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => '3',
                'reason' => 'Merma registrada',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHas('success');

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'quantity' => 3,
            'stock_before' => 8,
            'stock_after' => 5,
            'reason' => 'Merma registrada',
        ]);
    }

    public function test_admin_cannot_register_stock_decrease_above_available_stock(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 2,
        ]);

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.movements.store', $product), [
                'movement_product_id' => $product->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => '3',
                'reason' => 'Salida manual',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHasErrors(['quantity'], null, 'movement');

        $this->assertSame(2, $product->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_admin_can_adjust_product_stock_from_stock_page(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
        ]);

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.movements.store', $product), [
                'movement_product_id' => $product->id,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'new_stock' => '12',
                'reason' => 'Conteo fisico',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHas('success');

        $this->assertSame(12, $product->refresh()->stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_ADJUSTMENT,
            'quantity' => 4,
            'stock_before' => 8,
            'stock_after' => 12,
            'reason' => 'Conteo fisico',
        ]);
    }

    public function test_inventory_movement_requires_reason_and_valid_quantity(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
        ]);

        $this->from(route('admin.stock.index'))
            ->post(route('admin.stock.movements.store', $product), [
                'movement_product_id' => $product->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => '0',
                'reason' => '',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHasErrors(['quantity', 'reason'], null, 'movement');

        $this->assertSame(8, $product->refresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_registered_inventory_movement_is_visible_in_history(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
        ]);

        $this->post(route('admin.stock.movements.store', $product), [
            'movement_product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'quantity' => '2',
            'reason' => 'Compra a proveedor',
            'reference' => 'OC-1002',
        ]);

        $this->get(route('admin.stock.movements.index', $product))
            ->assertOk()
            ->assertSee('Compra a proveedor')
            ->assertSee('OC-1002')
            ->assertSee('Ingreso');
    }

    public function test_admin_can_update_low_stock_threshold_from_stock_page(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
            'low_stock_threshold' => 5,
        ]);

        $this->from(route('admin.stock.index'))
            ->patch(route('admin.stock.threshold.update', $product), [
                'threshold_product_id' => $product->id,
                'low_stock_threshold' => '12',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'low_stock_threshold' => 12,
        ]);
    }

    public function test_low_stock_threshold_must_not_be_negative(): void
    {
        [$category] = $this->catalogRelations();

        $product = $this->product([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'stock' => 8,
            'low_stock_threshold' => 5,
        ]);

        $this->from(route('admin.stock.index'))
            ->patch(route('admin.stock.threshold.update', $product), [
                'threshold_product_id' => $product->id,
                'low_stock_threshold' => '-1',
            ])
            ->assertRedirect(route('admin.stock.index'))
            ->assertSessionHasErrors(['low_stock_threshold'], null, 'threshold_'.$product->id);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'low_stock_threshold' => 5,
        ]);
    }

    private function catalogRelations(): array
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'suplementos'],
            [
                'name' => 'Suplementos',
                'is_active' => true,
                'is_featured' => true,
            ]
        );

        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'good-nature'],
            [
                'name' => 'Good Nature',
                'is_active' => true,
            ]
        );

        return [$category, $brand];
    }

    private function product(array $attributes): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Producto',
            'slug' => 'producto-'.uniqid(),
            'sku' => 'VN-PROD-'.uniqid(),
            'price' => 10,
            'stock' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ], $attributes));
    }
}
