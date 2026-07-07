<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_home_displays_featured_catalog_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Categorias destacadas')
            ->assertSee('Omega 3 Premium');
    }

    public function test_catalog_displays_paginated_products(): void
    {
        $this->get('/catalogo')
            ->assertOk()
            ->assertSee('Mostrando')
            ->assertSee('Omega 3 Premium');
    }

    public function test_catalog_can_filter_by_category_and_search_term(): void
    {
        $this->get('/catalogo?categoria=suplementos&q=omega')
            ->assertOk()
            ->assertSee('Categoria: Suplementos')
            ->assertSee('Omega 3 Premium');
    }

    public function test_catalog_can_filter_by_multiple_categories_and_brands(): void
    {
        $this->get('/catalogo?categoria[]=suplementos&categoria[]=vitaminas&marca[]=good-nature')
            ->assertOk()
            ->assertSee('Categoria: Suplementos')
            ->assertSee('Categoria: Vitaminas')
            ->assertSee('Marca: Good Nature')
            ->assertSee('Omega 3 Premium')
            ->assertSee('Magnesio citrato')
            ->assertSee('Complejo B')
            ->assertDontSee('Maca negra en polvo');
    }

    public function test_navbar_shows_only_active_categories_and_clean_filter_links(): void
    {
        Category::query()
            ->where('slug', 'snacks-saludables')
            ->update(['is_active' => false]);

        $response = $this->get('/catalogo?marca[]=good-nature&q=omega')
            ->assertOk()
            ->assertSee('data-category-menu-toggle', false)
            ->assertSee('id="mobileCategoryMenu"', false)
            ->assertSee('data-category-menu-panel', false)
            ->assertSee('/catalogo?categoria=suplementos', false)
            ->assertSee('/catalogo?oferta=1', false)
            ->assertSee('bi-prescription2', false)
            ->assertDontSee('overflow-auto', false)
            ->assertDontSee('Snacks saludables');

        $this->assertSame(2, substr_count($response->getContent(), 'bi bi-tag'));
    }

    public function test_catalog_can_filter_offers_from_navbar_or_checkbox(): void
    {
        $this->get('/catalogo?oferta=1')
            ->assertOk()
            ->assertSee('Ofertas')
            ->assertSee('Omega 3 Premium')
            ->assertSee('Vitamina C 1000 mg')
            ->assertDontSee('Maca negra en polvo')
            ->assertDontSee('Complejo B');
    }

    public function test_facet_counts_follow_offer_filter(): void
    {
        $html = $this->get('/catalogo?oferta=1&categoria[]=suplementos')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Good Nature\s*<span class="text-muted">\(2\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Nutrex Peru\s*<span class="text-muted">\(0\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Bio Energy\s*<span class="text-muted">\(1\)<\/span>/', $html);
    }

    public function test_brand_counts_follow_selected_categories(): void
    {
        $html = $this->get('/catalogo?categoria[]=suplementos')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Good Nature\s*<span class="text-muted">\(2\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Nutrex Peru\s*<span class="text-muted">\(1\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Amazonia Harvest\s*<span class="text-muted">\(0\)<\/span>/', $html);
    }

    public function test_category_counts_follow_selected_brands(): void
    {
        $html = $this->get('/catalogo?marca[]=good-nature')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Vitaminas\s*<span class="text-muted">\(1\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Suplementos\s*<span class="text-muted">\(2\)<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Superfoods\s*<span class="text-muted">\(0\)<\/span>/', $html);
    }

    public function test_product_detail_loads_by_slug(): void
    {
        $this->get('/producto/omega-3-premium')
            ->assertOk()
            ->assertSee('Omega 3 Premium')
            ->assertSee('Productos relacionados');
    }

    public function test_public_stock_label_uses_global_threshold(): void
    {
        Setting::setValue(Setting::PUBLIC_STOCK_DISPLAY_THRESHOLD, 10);
        $product = Product::query()->where('slug', 'omega-3-premium')->firstOrFail();

        $product->update(['stock' => 15]);
        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('En stock')
            ->assertDontSee('Mas de 10 disponibles');

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('Mas de 10 disponibles');

        $product->update(['stock' => 7]);
        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('Quedan pocas unidades')
            ->assertDontSee('Quedan 7 unidades');

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('Quedan 7 unidades')
            ->assertSee('text-warning', false);

        Setting::setValue(Setting::PUBLIC_STOCK_DISPLAY_THRESHOLD, 0);
        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('En stock')
            ->assertDontSee('Quedan 7 unidades')
            ->assertDontSee('Mas de 10 disponibles');
    }

    public function test_public_views_mark_out_of_stock_products(): void
    {
        $product = Product::query()->where('slug', 'omega-3-premium')->firstOrFail();
        $product->update(['stock' => 0]);

        $this->get(route('shop.catalog'))
            ->assertOk()
            ->assertSee('Sin stock')
            ->assertSee('is-out-of-stock', false)
            ->assertSee('text-bg-danger', false);

        $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('Sin stock')
            ->assertSee('No disponible')
            ->assertSee('disabled', false);
    }

    public function test_product_detail_gallery_shows_primary_image_first_and_no_video_button(): void
    {
        $product = Product::query()->where('slug', 'omega-3-premium')->firstOrFail();
        $product->images()->delete();

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/products/gallery-first.jpg',
            'alt_text' => 'Galeria primero por orden',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/products/main-late.jpg',
            'alt_text' => 'Imagen principal',
            'is_primary' => true,
            'sort_order' => 10,
        ]);

        $response = $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('data-product-gallery', false)
            ->assertSee('data-product-gallery-main', false)
            ->assertSee('data-product-gallery-open', false)
            ->assertSee('data-product-gallery-modal-image', false)
            ->assertDontSee('Ver video')
            ->assertDontSee('bi-play-circle-fill', false);

        $html = $response->getContent();

        $this->assertStringContainsString("background-image: url('/storage/products/main-late.jpg')", $html);
        $this->assertLessThan(
            strpos($html, '/storage/products/gallery-first.jpg'),
            strpos($html, '/storage/products/main-late.jpg')
        );
    }

    public function test_product_detail_uses_default_main_image_when_only_gallery_exists(): void
    {
        $product = Product::query()->where('slug', 'omega-3-premium')->firstOrFail();
        $product->images()->delete();

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/products/gallery-only.jpg',
            'alt_text' => 'Solo galeria',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('shop.product', $product->slug))
            ->assertOk()
            ->assertSee('/images/placeholders/products/default-prod.webp')
            ->assertSee('/storage/products/gallery-only.jpg');

        $html = $response->getContent();

        $this->assertLessThan(
            strpos($html, '/storage/products/gallery-only.jpg'),
            strpos($html, '/images/placeholders/products/default-prod.webp')
        );
    }

    public function test_unknown_product_slug_returns_not_found(): void
    {
        $this->get('/producto/no-existe')
            ->assertNotFound();
    }

    public function test_products_from_inactive_categories_are_hidden_from_public_catalog(): void
    {
        Category::query()
            ->where('slug', 'suplementos')
            ->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium');

        $this->get('/catalogo')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium');

        $this->get('/catalogo?categoria=suplementos&q=omega')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium');

        $this->get('/producto/omega-3-premium')
            ->assertNotFound();
    }

    public function test_products_from_inactive_brands_are_hidden_from_public_catalog(): void
    {
        Brand::query()
            ->where('slug', 'good-nature')
            ->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium')
            ->assertDontSee('Good Nature');

        $this->get('/catalogo')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium')
            ->assertDontSee('Good Nature');

        $this->get('/catalogo?marca=good-nature')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium');

        $this->get('/producto/omega-3-premium')
            ->assertNotFound();
    }

    public function test_related_products_skip_products_from_inactive_categories(): void
    {
        $product = Product::query()->where('slug', 'magnesio-citrato')->firstOrFail();
        $product->update(['category_id' => Category::query()->where('slug', 'superfoods')->value('id')]);

        Category::query()
            ->where('slug', 'suplementos')
            ->update(['is_active' => false]);

        $this->get('/producto/magnesio-citrato')
            ->assertOk()
            ->assertDontSee('Omega 3 Premium');
    }
}
