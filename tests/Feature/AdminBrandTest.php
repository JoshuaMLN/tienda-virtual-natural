<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesAdmins;
use Tests\TestCase;

class AdminBrandTest extends TestCase
{
    use AuthenticatesAdmins;
    use RefreshDatabase;

    public function test_admin_can_view_brand_list(): void
    {
        Brand::query()->create([
            'name' => 'Good Nature',
            'slug' => 'good-nature',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('Marcas')
            ->assertSee('Good Nature');
    }

    public function test_admin_can_filter_brands(): void
    {
        Brand::query()->create([
            'name' => 'Good Nature',
            'slug' => 'good-nature',
            'is_active' => true,
        ]);

        Brand::query()->create([
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'is_active' => false,
        ]);

        $this->get(route('admin.brands.index', [
            'q' => 'good',
            'estado' => 'activo',
        ]))
            ->assertOk()
            ->assertSee('Good Nature')
            ->assertDontSee('Bio Energy');
    }

    public function test_admin_brand_list_shows_responsive_filtered_summary(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Good Nature',
            'slug' => 'good-nature',
            'is_active' => true,
        ]);

        Brand::query()->create([
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'is_active' => false,
        ]);

        $category = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 12,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('2 marcas registradas')
            ->assertSee('Resumen segun los filtros actuales.')
            ->assertSee('admin-summary-chips', false)
            ->assertSee('data-bs-target="#brandSummaryMobile"', false)
            ->assertSee('data-summary-stat="active"', false)
            ->assertSee('data-summary-stat="inactive"', false)
            ->assertSee('data-summary-stat="with-products"', false)
            ->assertSee('data-summary-stat="without-products"', false);

        $this->get(route('admin.brands.index', ['q' => 'good']))
            ->assertOk()
            ->assertSee('Mostrando 1 de 2 marcas')
            ->assertSee('data-summary-stat="active"', false)
            ->assertSee('data-summary-stat="with-products"', false)
            ->assertDontSee('data-summary-stat="inactive"', false)
            ->assertDontSee('data-summary-stat="without-products"', false);
    }

    public function test_admin_brand_form_places_logo_cropper_in_right_column(): void
    {
        $this->get(route('admin.brands.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Nombre',
                'Slug',
                'Orden',
                'Marca activa',
                'Logo de marca',
            ])
            ->assertSee('admin-media-upload-panel', false)
            ->assertSee('cropper-frame-brand', false)
            ->assertSee('data-cropper-placeholder', false)
            ->assertSee('Sube una imagen para ver su vista previa');
    }

    public function test_admin_brand_edit_cropper_shows_current_logo_preview(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'logo_path' => 'brands/bio-energy.jpg',
            'is_active' => true,
        ]);

        $this->get(route('admin.brands.edit', $brand))
            ->assertOk()
            ->assertSee('data-cropper-preview-url=', false)
            ->assertSee('/storage/brands/bio-energy.jpg', false)
            ->assertSee('Hay un logo cargado. Puedes reemplazarlo subiendo uno nuevo.')
            ->assertSee('data-cropper-placeholder', false);
    }

    public function test_admin_can_create_brand_with_generated_slug(): void
    {
        $this->post(route('admin.brands.store'), [
            'name' => 'Amazonia Harvest',
            'is_active' => '1',
            'sort_order' => '20',
        ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', [
            'name' => 'Amazonia Harvest',
            'slug' => 'amazonia-harvest',
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    public function test_generated_brand_slug_uses_available_suffix(): void
    {
        Brand::query()->create(['name' => 'Good Nature', 'slug' => 'good-nature']);
        Brand::query()->create(['name' => 'Good Nature 2', 'slug' => 'good-nature-2']);

        $this->post(route('admin.brands.store'), [
            'name' => 'Good Nature',
            'is_active' => '1',
        ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', [
            'name' => 'Good Nature',
            'slug' => 'good-nature-3',
        ]);
    }

    public function test_admin_can_request_suggested_brand_slug(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Good Nature',
            'slug' => 'good-nature',
        ]);

        $this->getJson(route('admin.brands.suggest-slug', ['name' => 'Good Nature']))
            ->assertOk()
            ->assertJson(['slug' => 'good-nature-2']);

        $this->getJson(route('admin.brands.suggest-slug', [
            'name' => 'Good Nature',
            'ignore' => $brand->id,
        ]))
            ->assertOk()
            ->assertJson(['slug' => 'good-nature']);
    }

    public function test_admin_can_update_brand(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Nutrex',
            'slug' => 'nutrex',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->put(route('admin.brands.update', $brand), [
            'name' => 'Nutrex Peru',
            'slug' => 'nutrex-peru',
            'is_active' => '0',
            'sort_order' => '30',
        ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Nutrex Peru',
            'slug' => 'nutrex-peru',
            'is_active' => false,
            'sort_order' => 30,
        ]);
    }

    public function test_admin_can_upload_brand_logo(): void
    {
        Storage::fake('public');

        $this->post(route('admin.brands.store'), [
            'name' => 'Bio Energy',
            'logo' => UploadedFile::fake()->image('bio-energy.jpg', 1200, 675),
            'is_active' => '1',
        ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHasNoErrors();

        $brand = Brand::query()->where('slug', 'bio-energy')->firstOrFail();

        $this->assertNotNull($brand->logo_path);
        Storage::disk('public')->assertExists($brand->logo_path);
    }

    public function test_admin_can_remove_brand_logo(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('brands/old.jpg', 'old logo');

        $brand = Brand::query()->create([
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'logo_path' => 'brands/old.jpg',
            'logo_url' => 'https://example.com/old.jpg',
            'is_active' => true,
        ]);

        $this->put(route('admin.brands.update', $brand), [
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'is_active' => '1',
            'remove_logo' => '1',
        ])
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHasNoErrors();

        $brand->refresh();

        $this->assertNull($brand->logo_path);
        $this->assertNull($brand->logo_url);
        Storage::disk('public')->assertMissing('brands/old.jpg');
    }

    public function test_admin_can_toggle_brand_status(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Bio Energy',
            'slug' => 'bio-energy',
            'is_active' => true,
        ]);

        $this->patch(route('admin.brands.toggle-status', $brand))
            ->assertRedirect();

        $this->assertFalse($brand->refresh()->is_active);
    }

    public function test_admin_cannot_delete_brand_with_products(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Good Nature',
            'slug' => 'good-nature',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79,
            'stock' => 12,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->delete(route('admin.brands.destroy', $brand))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'slug' => 'good-nature',
        ]);
    }

    public function test_admin_can_delete_brand_without_products(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Temporal',
            'slug' => 'temporal',
            'is_active' => false,
        ]);

        $this->delete(route('admin.brands.destroy', $brand))
            ->assertRedirect(route('admin.brands.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('brands', [
            'id' => $brand->id,
        ]);
    }
}
