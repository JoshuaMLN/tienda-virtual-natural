<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_product_list_with_real_data(): void
    {
        [$category, $brand] = $this->catalogRelations();

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 45,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Productos')
            ->assertSee('Omega 3 Premium')
            ->assertSee('VN-OMEGA-120')
            ->assertSee('Suplementos')
            ->assertSee('Good Nature');
    }

    public function test_admin_can_filter_products(): void
    {
        [$category, $brand] = $this->catalogRelations();
        $otherCategory = Category::query()->create([
            'name' => 'Superfoods',
            'slug' => 'superfoods',
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 45,
            'is_active' => true,
            'published_at' => now(),
        ]);

        Product::query()->create([
            'category_id' => $otherCategory->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-200',
            'price' => 34.90,
            'stock' => 8,
            'is_active' => false,
        ]);

        $this->get(route('admin.products.index', [
            'q' => 'omega',
            'categoria' => $category->id,
            'marca' => $brand->id,
            'estado' => 'activo',
            'publicacion' => 'publicado',
        ]))
            ->assertOk()
            ->assertSee('Omega 3 Premium')
            ->assertDontSee('Maca Negra');
    }

    public function test_admin_product_edit_gallery_images_can_be_previewed(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $product->images()->create([
            'url' => '/storage/products/gallery.jpg',
            'alt_text' => 'Galeria Omega',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Imagenes del producto')
            ->assertSee('Subir imagen principal')
            ->assertSee('Subir imagen adicional')
            ->assertSeeInOrder([
                'Imagen principal',
                'Galeria Omega',
            ])
            ->assertSee('admin-gallery-grid', false)
            ->assertSee('admin-gallery-card', false)
            ->assertSee('admin-gallery-preview', false)
            ->assertSee(Product::DEFAULT_IMAGE, false)
            ->assertSee('data-cropper-placeholder', false)
            ->assertSee('Sube una imagen para ver su vista previa')
            ->assertSee('data-image-preview', false)
            ->assertSee('data-image-url="/storage/products/gallery.jpg"', false)
            ->assertSee('adminImagePreviewModal', false)
            ->assertSee('data-image-preview-modal-image', false);
    }

    public function test_admin_product_form_prioritizes_core_sections_and_marks_required_fields(): void
    {
        $this->catalogRelations();

        $this->get(route('admin.products.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Datos principales',
                'Venta y visibilidad',
                'Contenido del producto',
                'Imagenes del producto',
            ])
            ->assertSee('d-flex flex-column gap-3', false)
            ->assertSee('required-mark', false)
            ->assertSee('Nombre <span class="required-mark"', false)
            ->assertSee('SKU <span class="required-mark"', false)
            ->assertSee('Categoria <span class="required-mark"', false)
            ->assertSee('Precio <span class="required-mark"', false)
            ->assertSee('Podras agregar imagenes adicionales despues de guardar');
    }

    public function test_product_form_opens_only_section_with_validation_error(): void
    {
        [$category] = $this->catalogRelations();

        $this->followingRedirects()
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), [
                'category_id' => $category->id,
                'name' => 'Omega 3 Premium',
                'sku' => 'VN-OMEGA-120',
                'is_active' => '1',
            ])
            ->assertOk()
            ->assertSeeInOrder([
                'data-section-open="false"',
                'Datos principales',
                'data-section-open="true"',
                'Venta y visibilidad',
                'data-section-open="false"',
                'Contenido del producto',
            ])
            ->assertSee('aria-controls="product-sales-section"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertSee('Imagenes del producto');
    }

    public function test_admin_can_create_product_with_generated_slug(): void
    {
        [$category, $brand] = $this->catalogRelations();

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Colageno Hidrolizado',
            'sku' => 'VN-COLA-200',
            'short_description' => 'Sobre 200 g',
            'description' => 'Apoya la salud de la piel.',
            'benefits' => 'Piel, cabello y articulaciones.',
            'ingredients' => 'Colageno hidrolizado.',
            'usage_instructions' => 'Tomar una porcion diaria.',
            'price' => '69.90',
            'compare_at_price' => '79.90',
            'stock' => '24',
            'is_active' => '1',
            'is_featured' => '1',
            'published_at' => now()->format('Y-m-d H:i:s'),
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Colageno Hidrolizado',
            'slug' => 'colageno-hidrolizado',
            'sku' => 'VN-COLA-200',
            'price' => 69.90,
            'compare_at_price' => 79.90,
            'stock' => 24,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    public function test_generated_product_slug_uses_available_suffix(): void
    {
        [$category] = $this->catalogRelations();

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'sku' => 'VN-OMEGA-121',
            'price' => '89.90',
            'is_active' => '1',
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium-2',
            'sku' => 'VN-OMEGA-121',
        ]);
    }

    public function test_admin_can_request_suggested_product_slug(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $this->getJson(route('admin.products.suggest-slug', ['name' => 'Omega 3 Premium']))
            ->assertOk()
            ->assertJson(['slug' => 'omega-3-premium-2']);

        $this->getJson(route('admin.products.suggest-slug', [
            'name' => 'Omega 3 Premium',
            'ignore' => $product->id,
        ]))
            ->assertOk()
            ->assertJson(['slug' => 'omega-3-premium']);
    }

    public function test_admin_can_update_product(): void
    {
        [$category, $brand] = $this->catalogRelations();
        $newCategory = Category::query()->create([
            'name' => 'Vitaminas',
            'slug' => 'vitaminas',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3',
            'slug' => 'omega-3',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'stock' => 45,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->put(route('admin.products.update', $product), [
            'category_id' => $newCategory->id,
            'brand_id' => '',
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-121',
            'short_description' => '120 capsulas',
            'price' => '89.90',
            'compare_at_price' => '',
            'stock' => '30',
            'is_active' => '0',
            'is_featured' => '1',
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category_id' => $newCategory->id,
            'brand_id' => null,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-121',
            'price' => 89.90,
            'compare_at_price' => null,
            'stock' => 30,
            'is_active' => false,
            'is_featured' => true,
        ]);
    }

    public function test_admin_can_toggle_product_status_and_publication(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'is_active' => true,
            'published_at' => null,
        ]);

        $this->patch(route('admin.products.toggle-status', $product))
            ->assertRedirect();

        $this->assertFalse($product->refresh()->is_active);

        $this->patch(route('admin.products.toggle-publication', $product))
            ->assertRedirect();

        $this->assertNotNull($product->refresh()->published_at);

        $this->patch(route('admin.products.toggle-publication', $product))
            ->assertRedirect();

        $this->assertNull($product->refresh()->published_at);
    }

    public function test_admin_can_delete_product(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Temporal',
            'slug' => 'temporal',
            'sku' => 'VN-TEMP',
            'price' => 10,
        ]);

        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_product_created_from_admin_is_visible_in_public_catalog_when_published(): void
    {
        [$category, $brand] = $this->catalogRelations();

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Vitamina D3 2000 UI',
            'sku' => 'VN-D3-90',
            'short_description' => '90 capsulas',
            'price' => '49.90',
            'stock' => '18',
            'is_active' => '1',
            'is_featured' => '1',
            'published_at' => now()->format('Y-m-d H:i:s'),
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Vitamina D3 2000 UI');

        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('Vitamina D3 2000 UI');

        $this->get(route('shop.product', 'vitamina-d3-2000-ui'))
            ->assertOk()
            ->assertSee('Vitamina D3 2000 UI');
    }

    public function test_product_without_images_uses_local_default_image(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Producto sin imagen',
            'slug' => 'producto-sin-imagen',
            'sku' => 'VN-SIN-IMG',
            'price' => 10,
        ]);

        $this->assertStringEndsWith('/images/placeholders/products/default-prod.webp', $product->main_image_url);

        $product->images()->create([
            'url' => '/storage/products/gallery-only.jpg',
            'alt_text' => 'Gallery only',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->assertStringEndsWith('/images/placeholders/products/default-prod.webp', $product->refresh()->main_image_url);
    }

    public function test_inactive_or_unpublished_products_are_hidden_from_public_catalog(): void
    {
        [$category, $brand] = $this->catalogRelations();

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto Inactivo',
            'slug' => 'producto-inactivo',
            'sku' => 'VN-INACTIVO',
            'price' => 10,
            'is_active' => false,
            'published_at' => now(),
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto Sin Publicar',
            'slug' => 'producto-sin-publicar',
            'sku' => 'VN-SIN-PUBLICAR',
            'price' => 20,
            'is_active' => true,
            'published_at' => null,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Producto Futuro',
            'slug' => 'producto-futuro',
            'sku' => 'VN-FUTURO',
            'price' => 30,
            'is_active' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertDontSee('Producto Inactivo')
            ->assertDontSee('Producto Sin Publicar')
            ->assertDontSee('Producto Futuro');

        $this->get(route('shop.product', 'producto-inactivo'))->assertNotFound();
        $this->get(route('shop.product', 'producto-sin-publicar'))->assertNotFound();
        $this->get(route('shop.product', 'producto-futuro'))->assertNotFound();
    }

    public function test_admin_can_upload_main_product_image_when_creating_product(): void
    {
        Storage::fake('public');
        [$category, $brand] = $this->catalogRelations();

        $this->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Omega 3 Premium',
            'sku' => 'VN-OMEGA-120',
            'price' => '79.90',
            'is_active' => '1',
            'published_at' => now()->format('Y-m-d H:i:s'),
            'main_image' => UploadedFile::fake()->image('omega.jpg', 900, 900),
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', 'omega-3-premium')->firstOrFail();
        $image = $product->images()->firstOrFail();

        $this->assertTrue($image->is_primary);
        $this->assertNotNull($image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_admin_can_update_main_product_image_from_media_section(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'is_active' => true,
        ]);

        $this->patch(route('admin.products.main-image.update', $product), [
            'main_image' => UploadedFile::fake()->image('omega-main.jpg', 900, 900),
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $image = $product->images()->firstOrFail();

        $this->assertTrue($image->is_primary);
        $this->assertNotNull($image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_admin_can_remove_main_product_image(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'is_active' => true,
        ]);

        Storage::disk('public')->put('products/old.jpg', 'old image');
        $product->images()->create([
            'image_path' => 'products/old.jpg',
            'url' => '/storage/products/old.jpg',
            'alt_text' => $product->name,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => '79.90',
            'is_active' => '1',
            'remove_main_image' => '1',
        ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->images()->exists());
        Storage::disk('public')->assertMissing('products/old.jpg');
    }

    public function test_admin_can_remove_main_product_image_from_media_section(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
            'is_active' => true,
        ]);

        Storage::disk('public')->put('products/old.jpg', 'old image');
        $product->images()->create([
            'image_path' => 'products/old.jpg',
            'url' => '/storage/products/old.jpg',
            'alt_text' => $product->name,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSeeInOrder([
                'Imagen principal',
                'Subido',
                'Eliminar',
            ])
            ->assertSee('data-cropper-preview-url="/storage/products/old.jpg"', false)
            ->assertSee('data-cropper-placeholder', false)
            ->assertSee(route('admin.products.main-image.update', $product), false)
            ->assertSee('name="remove_main_image" type="hidden" value="1"', false)
            ->assertDontSee('Quitar imagen principal');

        $this->patch(route('admin.products.main-image.update', $product), [
            'remove_main_image' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($product->images()->exists());
        Storage::disk('public')->assertMissing('products/old.jpg');
    }

    public function test_admin_can_add_gallery_image_without_making_it_primary(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $firstImage = $product->images()->create([
            'url' => 'https://example.com/old.jpg',
            'alt_text' => 'Old',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $this->post(route('admin.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('omega-extra.jpg', 900, 900),
            'alt_text' => 'Omega extra',
            'sort_order' => '2',
            'is_primary' => '1',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $newImage = $product->images()->where('alt_text', 'Omega extra')->firstOrFail();

        $this->assertFalse($newImage->is_primary);
        $this->assertTrue($firstImage->refresh()->is_primary);
        Storage::disk('public')->assertExists($newImage->image_path);

        $this->patch(route('admin.products.images.primary', [$product, $newImage]))
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertFalse($newImage->refresh()->is_primary);
        $this->assertTrue($firstImage->refresh()->is_primary);
    }

    public function test_gallery_image_required_error_is_human_readable(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $this->from(route('admin.products.edit', $product))
            ->post(route('admin.products.images.store', $product), [
                'alt_text' => 'Omega extra',
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors([
                'image' => 'Selecciona una imagen adicional antes de agregarla.',
            ]);
    }

    public function test_admin_can_delete_product_image(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        Storage::disk('public')->put('products/gallery.jpg', 'gallery');
        $image = $product->images()->create([
            'image_path' => 'products/gallery.jpg',
            'url' => '/storage/products/gallery.jpg',
            'alt_text' => 'Gallery',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $this->delete(route('admin.products.images.destroy', [$product, $image]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('product_images', [
            'id' => $image->id,
        ]);
        Storage::disk('public')->assertMissing('products/gallery.jpg');
    }

    public function test_deleting_primary_product_image_does_not_promote_gallery_image(): void
    {
        Storage::fake('public');
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        Storage::disk('public')->put('products/main.jpg', 'main');
        Storage::disk('public')->put('products/second.jpg', 'second');

        $mainImage = $product->images()->create([
            'image_path' => 'products/main.jpg',
            'url' => '/storage/products/main.jpg',
            'alt_text' => 'Main',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $secondImage = $product->images()->create([
            'image_path' => 'products/second.jpg',
            'url' => '/storage/products/second.jpg',
            'alt_text' => 'Second',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        $this->delete(route('admin.products.images.destroy', [$product, $mainImage]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($secondImage->refresh()->is_primary);
        $this->assertStringEndsWith('/images/placeholders/products/default-prod.webp', $product->refresh()->main_image_url);
        Storage::disk('public')->assertMissing('products/main.jpg');
        Storage::disk('public')->assertExists('products/second.jpg');
    }

    public function test_product_image_actions_require_matching_product(): void
    {
        [$category] = $this->catalogRelations();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3 Premium',
            'slug' => 'omega-3-premium',
            'sku' => 'VN-OMEGA-120',
            'price' => 79.90,
        ]);

        $otherProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Maca Negra',
            'slug' => 'maca-negra',
            'sku' => 'VN-MACA-200',
            'price' => 34.90,
        ]);

        $image = ProductImage::query()->create([
            'product_id' => $otherProduct->id,
            'url' => 'https://example.com/image.jpg',
            'is_primary' => true,
        ]);

        $this->patch(route('admin.products.images.primary', [$product, $image]))
            ->assertNotFound();

        $this->delete(route('admin.products.images.destroy', [$product, $image]))
            ->assertNotFound();
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
}
