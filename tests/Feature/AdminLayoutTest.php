<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_mobile_sidebar_has_open_close_and_backdrop_controls(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-sidebar', false)
            ->assertSee('data-admin-sidebar-close', false)
            ->assertSee('admin-sidebar-backdrop', false)
            ->assertSee('aria-label="Cerrar menu"', false);
    }

    public function test_admin_dashboard_uses_real_product_stock_data(): void
    {
        $category = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 3,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Zinc Agotado',
            'slug' => 'zinc-agotado',
            'sku' => 'VN-ZINC-0',
            'price' => 29.90,
            'stock' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-200',
            'price' => 34.90,
            'stock' => 40,
            'low_stock_threshold' => 5,
            'is_active' => true,
            'published_at' => null,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Producto Inactivo',
            'slug' => 'producto-inactivo',
            'sku' => 'VN-INACTIVO',
            'price' => 10,
            'stock' => 2,
            'low_stock_threshold' => 5,
            'is_active' => false,
        ]);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Productos activos')
            ->assertSee('3')
            ->assertSee('2 visibles en tienda')
            ->assertSee('Productos con alerta de stock')
            ->assertSee('Omega 3 Premium')
            ->assertSee('3 und.')
            ->assertSee('Zinc Agotado')
            ->assertSee('Sin stock')
            ->assertSeeInOrder(['Omega 3 Premium', 'Zinc Agotado'])
            ->assertDontSee('Maca Negra')
            ->assertDontSee('Producto Inactivo');
    }
}
