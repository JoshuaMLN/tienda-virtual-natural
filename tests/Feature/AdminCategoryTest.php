<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesAdmins;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use AuthenticatesAdmins;
    use RefreshDatabase;

    public function test_admin_can_view_category_list(): void
    {
        Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Categorias')
            ->assertSee('Suplementos');
    }

    public function test_category_without_image_uses_local_default_image(): void
    {
        $category = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);

        $this->assertFalse($category->has_custom_image);
        $this->assertStringEndsWith('/images/placeholders/categories/default-cat.webp', $category->image_source);
    }

    public function test_admin_category_form_places_image_cropper_in_right_column(): void
    {
        $this->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Nombre',
                'Slug',
                'Descripcion',
                'Icono',
                'Orden',
                'Categoria activa',
                'Mostrar destacada',
                'Imagen de categoria',
            ])
            ->assertSee('admin-media-upload-panel', false)
            ->assertSee('data-cropper-placeholder', false)
            ->assertSee('Sube una imagen para ver su vista previa');
    }

    public function test_admin_category_edit_cropper_shows_current_image_preview(): void
    {
        $category = Category::query()->create([
            'name' => 'Infusiones',
            'slug' => 'infusiones',
            'image_path' => 'categories/infusiones.jpg',
            'icon_class' => 'bi-cup-hot',
            'is_active' => true,
        ]);

        $this->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertSee('data-cropper-preview-url=', false)
            ->assertSee('/storage/categories/infusiones.jpg', false)
            ->assertSee('Hay una imagen cargada. Puedes reemplazarla subiendo una nueva.')
            ->assertSee('data-cropper-placeholder', false);
    }

    public function test_admin_can_filter_categories(): void
    {
        Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);

        Category::query()->create([
            'name' => 'Belleza Natural',
            'slug' => 'belleza-natural',
            'is_active' => false,
        ]);

        $this->get(route('admin.categories.index', [
            'q' => 'suplementos',
            'estado' => 'activo',
        ]))
            ->assertOk()
            ->assertSee('Suplementos')
            ->assertDontSee('Belleza Natural');
    }

    public function test_admin_category_list_shows_responsive_filtered_summary(): void
    {
        $activeCategory = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
            'is_featured' => true,
        ]);

        Category::query()->create([
            'name' => 'Belleza Natural',
            'slug' => 'belleza-natural',
            'is_active' => false,
            'is_featured' => false,
        ]);

        Product::query()->create([
            'category_id' => $activeCategory->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 12,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('2 categorias registradas')
            ->assertSee('Resumen segun los filtros actuales.')
            ->assertSee('admin-summary-chips', false)
            ->assertSee('data-bs-target="#categorySummaryMobile"', false)
            ->assertSee('data-summary-stat="active"', false)
            ->assertSee('data-summary-stat="inactive"', false)
            ->assertSee('data-summary-stat="featured"', false)
            ->assertSee('data-summary-stat="with-products"', false)
            ->assertSee('data-summary-stat="without-products"', false);

        $this->get(route('admin.categories.index', ['q' => 'suplementos']))
            ->assertOk()
            ->assertSee('Mostrando 1 de 2 categorias')
            ->assertSee('data-summary-stat="active"', false)
            ->assertSee('data-summary-stat="featured"', false)
            ->assertSee('data-summary-stat="with-products"', false)
            ->assertDontSee('data-summary-stat="inactive"', false)
            ->assertDontSee('data-summary-stat="without-products"', false);
    }

    public function test_admin_can_create_category_with_generated_slug(): void
    {
        $this->post(route('admin.categories.store'), [
            'name' => 'Belleza Natural',
            'description' => 'Cuidado personal saludable.',
            'icon_class' => 'bi-droplet',
            'is_active' => '1',
            'is_featured' => '1',
            'sort_order' => '20',
        ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Belleza Natural',
            'slug' => 'belleza-natural',
            'icon_class' => 'bi-droplet',
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 20,
        ]);
    }

    public function test_generated_category_slug_uses_available_suffix(): void
    {
        Category::query()->create(['name' => 'Belleza Natural', 'slug' => 'belleza-natural']);
        Category::query()->create(['name' => 'Belleza Natural 2', 'slug' => 'belleza-natural-2']);

        $this->post(route('admin.categories.store'), [
            'name' => 'Belleza Natural',
            'icon_class' => 'bi-droplet',
            'is_active' => '1',
        ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Belleza Natural',
            'slug' => 'belleza-natural-3',
        ]);
    }

    public function test_admin_can_request_suggested_category_slug(): void
    {
        $category = Category::query()->create([
            'name' => 'Belleza Natural',
            'slug' => 'belleza-natural',
        ]);

        $this->getJson(route('admin.categories.suggest-slug', ['name' => 'Belleza Natural']))
            ->assertOk()
            ->assertJson(['slug' => 'belleza-natural-2']);

        $this->getJson(route('admin.categories.suggest-slug', [
            'name' => 'Belleza Natural',
            'ignore' => $category->id,
        ]))
            ->assertOk()
            ->assertJson(['slug' => 'belleza-natural']);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::query()->create([
            'name' => 'Snacks',
            'slug' => 'snacks',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Snacks Saludables',
            'slug' => 'snacks-saludables',
            'description' => 'Opciones listas para llevar.',
            'icon_class' => 'bi-basket2',
            'is_active' => '0',
            'is_featured' => '1',
            'sort_order' => '30',
        ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Snacks Saludables',
            'slug' => 'snacks-saludables',
            'icon_class' => 'bi-basket2',
            'is_active' => false,
            'is_featured' => true,
            'sort_order' => 30,
        ]);
    }

    public function test_invalid_category_icon_is_rejected(): void
    {
        $this->post(route('admin.categories.store'), [
            'name' => 'Categoria rara',
            'icon_class' => 'bi-no-existe',
        ])
            ->assertSessionHasErrors('icon_class');
    }

    public function test_admin_can_upload_category_image(): void
    {
        Storage::fake('public');

        $this->post(route('admin.categories.store'), [
            'name' => 'Infusiones',
            'icon_class' => 'bi-cup-hot',
            'image' => UploadedFile::fake()->image('infusiones.jpg', 900, 600),
            'is_active' => '1',
        ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $category = Category::query()->where('slug', 'infusiones')->firstOrFail();

        $this->assertNotNull($category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
    }

    public function test_admin_can_remove_category_image(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('categories/old.jpg', 'old image');

        $category = Category::query()->create([
            'name' => 'Infusiones',
            'slug' => 'infusiones',
            'image_path' => 'categories/old.jpg',
            'image_url' => 'https://example.com/old.jpg',
            'icon_class' => 'bi-cup-hot',
            'is_active' => true,
        ]);

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Infusiones',
            'slug' => 'infusiones',
            'icon_class' => 'bi-cup-hot',
            'is_active' => '1',
            'remove_image' => '1',
        ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertNull($category->image_path);
        $this->assertNull($category->image_url);
        Storage::disk('public')->assertMissing('categories/old.jpg');
    }

    public function test_admin_can_toggle_category_status(): void
    {
        $category = Category::query()->create([
            'name' => 'Infusiones',
            'slug' => 'infusiones',
            'is_active' => true,
        ]);

        $this->patch(route('admin.categories.toggle-status', $category))
            ->assertRedirect();

        $this->assertFalse($category->refresh()->is_active);
    }

    public function test_admin_cannot_delete_category_with_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Vitaminas',
            'slug' => 'vitaminas',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Vitamina C 1000 mg',
            'slug' => 'vitamina-c-1000-mg',
            'sku' => 'VIT-C-1000',
            'price' => 59,
            'stock' => 12,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => 'vitaminas',
        ]);
    }

    public function test_admin_can_delete_category_without_products(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('categories/temporal.jpg', 'image');

        $category = Category::query()->create([
            'name' => 'Temporal',
            'slug' => 'temporal',
            'image_path' => 'categories/temporal.jpg',
            'is_active' => false,
        ]);

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
        Storage::disk('public')->assertMissing('categories/temporal.jpg');
    }
}
