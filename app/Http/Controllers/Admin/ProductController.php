<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaxAffectation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filteredProducts = $this->applyIndexFilters(Product::query(), $request);
        $productSummary = $this->productSummary($filteredProducts);

        $products = (clone $filteredProducts)
            ->with(['brand', 'category', 'primaryImage'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $brands = Brand::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.products.index', compact('brands', 'categories', 'productSummary', 'products'));
    }

    private function applyIndexFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('categoria'), function ($query) use ($request) {
                $query->where('category_id', $request->integer('categoria'));
            })
            ->when($request->filled('marca'), function ($query) use ($request) {
                $query->where('brand_id', $request->integer('marca'));
            })
            ->when($request->filled('estado'), function ($query) use ($request) {
                $query->where('is_active', $request->string('estado')->toString() === 'activo');
            })
            ->when($request->filled('publicacion'), function ($query) use ($request) {
                $pub = $request->string('publicacion')->toString();

                // Publicado: producto activo + published_at pasado + categoria activa + marca activa (o sin marca).
                // Es la misma regla que scopeActive() en Product.
                if ($pub === 'publicado') {
                    $query->active();
                }

                // Oculto: published_at pasado pero el producto, categoria o marca esta inactivo.
                if ($pub === 'oculto') {
                    $this->applyHiddenVisibilityFilter($query);
                }

                // Programado: published_at en el futuro.
                if ($pub === 'programado') {
                    $query->whereNotNull('published_at')
                        ->where('published_at', '>', now());
                }

                // Sin publicar: sin fecha (solo NULL, ya no mezcla con programados).
                if ($pub === 'sin-publicar') {
                    $query->whereNull('published_at');
                }
            });
    }

    private function productSummary(Builder $query): array
    {
        $stats = [
            [
                'key' => 'active',
                'label' => 'Activos',
                'value' => (clone $query)->where('is_active', true)->count(),
                'icon' => 'bi-toggle-on',
                'tone' => 'success',
            ],
            [
                'key' => 'published',
                'label' => 'Publicados',
                'value' => (clone $query)->active()->count(),
                'icon' => 'bi-shop',
                'tone' => 'success',
            ],
            [
                'key' => 'hidden',
                'label' => 'Ocultos',
                'value' => $this->applyHiddenVisibilityFilter(clone $query)->count(),
                'icon' => 'bi-eye-slash',
                'tone' => 'warning',
            ],
            [
                'key' => 'out-of-stock',
                'label' => 'Sin stock',
                'value' => (clone $query)->where('stock', '<=', 0)->count(),
                'icon' => 'bi-x-octagon',
                'tone' => 'danger',
            ],
            [
                'key' => 'scheduled',
                'label' => 'Programados',
                'value' => (clone $query)->whereNotNull('published_at')->where('published_at', '>', now())->count(),
                'icon' => 'bi-calendar-event',
                'tone' => 'info',
            ],
            [
                'key' => 'unpublished',
                'label' => 'Sin publicar',
                'value' => (clone $query)->whereNull('published_at')->count(),
                'icon' => 'bi-cloud-slash',
                'tone' => 'muted',
            ],
        ];

        $nonZeroStats = collect($stats)
            ->filter(fn (array $stat) => $stat['value'] > 0)
            ->values();

        return [
            'total' => Product::query()->count(),
            'filtered' => (clone $query)->count(),
            'stats' => $nonZeroStats->all(),
            'visible_stats' => $nonZeroStats->take(4)->all(),
        ];
    }

    private function applyHiddenVisibilityFilter(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function (Builder $query) {
                $query->where('is_active', false)
                    ->orWhereHas('category', fn (Builder $query) => $query->where('is_active', false))
                    ->orWhere(function (Builder $query) {
                        $query->whereNotNull('brand_id')
                            ->whereHas('brand', fn (Builder $query) => $query->where('is_active', false));
                    });
            });
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product([
                'is_active' => true,
                'is_featured' => false,
                'stock' => 0,
                'tax_affectation' => TaxAffectation::Taxed,
                'published_at' => now(),
            ]),
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'taxAffectations' => TaxAffectation::cases(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $product = Product::create($this->productAttributes($validated));
        $this->storeMainImage($product, $request);

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto {$product->name} creado correctamente.");
    }

    public function edit(Product $product): View
    {
        $product->load(['category', 'brand', 'images', 'primaryImage']);

        return view('admin.products.edit', [
            'product' => $product,
            'categories' => $this->categoryOptions(),
            'brands' => $this->brandOptions(),
            'taxAffectations' => TaxAffectation::cases(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $product->update($this->productAttributes($validated));
        $this->storeMainImage($product, $request);

        if ($request->boolean('remove_main_image') && ! $request->hasFile('main_image') && ! $request->filled('cropped_main_image')) {
            $this->deleteImage($product->primaryImage);
        }

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto {$product->name} actualizado correctamente.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $productName = $product->name;
        $product->images->each(fn (ProductImage $image) => $this->deleteImageFile($image));
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Producto {$productName} eliminado correctamente.");
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', "Estado de {$product->name} actualizado.");
    }

    public function togglePublication(Product $product): RedirectResponse
    {
        $product->update([
            'published_at' => $product->published_at ? null : now(),
        ]);

        return back()->with('success', "Publicacion de {$product->name} actualizada.");
    }

    public function updateMainImage(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'main_image' => ['nullable', 'image', 'max:4096'],
            'cropped_main_image' => ['nullable', 'string'],
            'remove_main_image' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('remove_main_image') && ! $request->hasFile('main_image') && ! $request->filled('cropped_main_image')) {
            $this->deleteImage($product->primaryImage);

            return back()->with('success', "Imagen principal de {$product->name} eliminada.");
        }

        $this->storeMainImage($product, $request);

        return back()->with('success', "Imagen principal de {$product->name} actualizada.");
    }

    public function storeImage(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate(
            [
                'image' => ['required', 'image', 'max:4096'],
                'cropped_image' => ['nullable', 'string'],
                'alt_text' => ['nullable', 'string', 'max:255'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            ],
            [
                'image.required' => 'Selecciona una imagen adicional antes de agregarla.',
                'image.image' => 'El archivo debe ser una imagen valida.',
                'image.max' => 'La imagen adicional no debe superar los 4 MB.',
                'alt_text.max' => 'El texto alternativo no debe superar los 255 caracteres.',
                'sort_order.integer' => 'El orden debe ser un numero entero.',
                'sort_order.min' => 'El orden no puede ser negativo.',
                'sort_order.max' => 'El orden no debe superar 65535.',
            ],
            [
                'image' => 'imagen adicional',
                'alt_text' => 'texto alternativo',
                'sort_order' => 'orden',
            ]
        );

        $this->storeProductImage(
            product: $product,
            request: $request,
            fileInput: 'image',
            croppedInput: 'cropped_image',
            altText: $validated['alt_text'] ?? $product->name,
            sortOrder: $validated['sort_order'] ?? $product->images()->max('sort_order') + 1
        );

        return back()->with('success', "Imagen agregada a {$product->name}.");
    }

    public function destroyImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $this->deleteImage($image);

        return back()->with('success', "Imagen eliminada de {$product->name}.");
    }

    public function makePrimaryImage(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        return back()->with('warning', 'La galeria se mantiene separada de la imagen principal. Sube una imagen principal desde el formulario del producto.');
    }

    public function suggestSlug(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ignore' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        return response()->json([
            'slug' => Product::uniqueSlug(
                $request->string('name')->toString(),
                $request->integer('ignore') ?: null
            ),
        ]);
    }

    private function categoryOptions()
    {
        return Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function brandOptions()
    {
        return Brand::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function productAttributes(array $validated): array
    {
        return Arr::except($validated, [
            'main_image',
            'cropped_main_image',
            'remove_main_image',
        ]);
    }

    private function storeMainImage(Product $product, Request $request): void
    {
        $image = $this->storeProductImage(
            product: $product,
            request: $request,
            fileInput: 'main_image',
            croppedInput: 'cropped_main_image',
            altText: $product->name,
            sortOrder: 0
        );

        if ($image) {
            $this->deleteImage($product->primaryImage);
            $this->setPrimaryImage($product, $image);
        }
    }

    private function storeProductImage(
        Product $product,
        Request $request,
        string $fileInput,
        string $croppedInput,
        string $altText,
        int $sortOrder
    ): ?ProductImage {
        $imageContents = null;
        $extension = 'jpg';

        if ($request->filled($croppedInput)) {
            $payload = $request->string($croppedInput)->toString();
            $extension = str_contains($payload, 'image/png') ? 'png' : 'jpg';
            $imageContents = base64_decode((string) Str::of($payload)->after(',')->toString(), true);
        } elseif ($request->hasFile($fileInput)) {
            $file = $request->file($fileInput);
            $extension = $file->extension() ?: 'jpg';
            $imageContents = file_get_contents($file->getRealPath());
        }

        if (! $imageContents) {
            return null;
        }

        $path = 'products/'.$product->id.'-'.Str::slug($product->name).'-'.Str::random(8).'.'.$extension;
        Storage::disk('public')->put($path, $imageContents);

        return $product->images()->create([
            'image_path' => $path,
            'url' => Storage::disk('public')->url($path),
            'alt_text' => $altText,
            'is_primary' => false,
            'sort_order' => $sortOrder,
        ]);
    }

    private function setPrimaryImage(Product $product, ProductImage $image): void
    {
        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    private function deleteImage(?ProductImage $image): void
    {
        if (! $image) {
            return;
        }

        $this->deleteImageFile($image);
        $image->delete();
    }

    private function deleteImageFile(ProductImage $image): void
    {
        if ($image->image_path) {
            Storage::disk('public')->delete($image->image_path);
        }
    }
}
