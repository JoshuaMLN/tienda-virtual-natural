<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Notifications\AdminNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesAdmins;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use AuthenticatesAdmins;
    use RefreshDatabase;

    public function test_stock_notifications_are_generated_for_active_products(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Brand', 'slug' => 'brand', 'is_active' => true]);

        // Activo sin stock -> 1 alerta (danger)
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prod 1',
            'slug' => 'prod-1',
            'sku' => 'SKU-1',
            'price' => 10,
            'is_active' => true,
            'stock' => 0,
        ]);

        // Activo con bajo stock -> 1 alerta (warning)
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prod 2',
            'slug' => 'prod-2',
            'sku' => 'SKU-2',
            'price' => 10,
            'is_active' => true,
            'stock' => 5,
            'low_stock_threshold' => 10,
        ]);

        // Inactivo sin stock -> 0 alertas (ignorado)
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prod 3',
            'slug' => 'prod-3',
            'sku' => 'SKU-3',
            'price' => 10,
            'is_active' => false,
            'stock' => 0,
        ]);

        // Activo stock normal -> 0 alertas (ignorado)
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prod 4',
            'slug' => 'prod-4',
            'sku' => 'SKU-4',
            'price' => 10,
            'is_active' => true,
            'stock' => 50,
            'low_stock_threshold' => 10,
        ]);

        $service = app(AdminNotificationService::class);
        $notifications = $service->getAll();

        $this->assertCount(2, $notifications);
        $this->assertEquals(2, $service->getCount());

        $this->assertEquals('danger', $notifications[0]->type);
        $this->assertEquals('Sin stock', $notifications[0]->title);
        $this->assertStringContainsString('1 producto activo agotado', $notifications[0]->message);

        $this->assertEquals('warning', $notifications[1]->type);
        $this->assertEquals('Bajo stock', $notifications[1]->title);
        $this->assertStringContainsString('1 producto activo por agotarse', $notifications[1]->message);
    }

    public function test_topbar_renders_notifications_correctly(): void
    {
        $category = Category::query()->create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Brand', 'slug' => 'brand', 'is_active' => true]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Prod 1',
            'slug' => 'prod-1',
            'sku' => 'SKU-1',
            'price' => 10,
            'is_active' => true,
            'stock' => 0,
        ]);

        $view = $this->view('components.admin.topbar');

        $view->assertSee('<span class="cart-count">1</span>', false);
        $view->assertSee('Sin stock');
        $view->assertSee('1 producto activo agotado');
    }

    public function test_topbar_renders_empty_state_when_no_notifications(): void
    {
        $view = $this->view('components.admin.topbar');

        $view->assertDontSee('<span class="cart-count">', false);
        $view->assertSee('Todo al dia');
    }
}
