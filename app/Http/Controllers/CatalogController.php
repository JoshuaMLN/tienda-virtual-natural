<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $requestedCategorySlugs = $this->selectedSlugs($request, 'categoria');
        $requestedBrandSlugs = $this->selectedSlugs($request, 'marca');

        $selectedCategories = Category::query()
            ->active()
            ->whereIn('slug', $requestedCategorySlugs)
            ->orderBy('sort_order')
            ->get();

        $selectedBrands = Brand::query()
            ->active()
            ->whereIn('slug', $requestedBrandSlugs)
            ->orderBy('sort_order')
            ->get();

        $selectedCategorySlugs = $selectedCategories->pluck('slug')->all();
        $selectedBrandSlugs = $selectedBrands->pluck('slug')->all();
        $hasInvalidCategoryFilter = $requestedCategorySlugs !== [] && $selectedCategories->isEmpty();
        $hasInvalidBrandFilter = $requestedBrandSlugs !== [] && $selectedBrands->isEmpty();

        $categories = Category::query()
            ->active()
            ->withCount(['products' => function (Builder $query) use ($request, $selectedBrandSlugs, $hasInvalidBrandFilter) {
                $this->applyProductFilters(
                    query: $query->active(),
                    request: $request,
                    brandSlugs: $selectedBrandSlugs,
                    hasInvalidBrandFilter: $hasInvalidBrandFilter
                );
            }])
            ->orderBy('sort_order')
            ->get();

        $brands = Brand::query()
            ->active()
            ->withCount(['products' => function (Builder $query) use ($request, $selectedCategorySlugs, $hasInvalidCategoryFilter) {
                $this->applyProductFilters(
                    query: $query->active(),
                    request: $request,
                    categorySlugs: $selectedCategorySlugs,
                    hasInvalidCategoryFilter: $hasInvalidCategoryFilter
                );
            }])
            ->orderBy('sort_order')
            ->get();

        $productsQuery = Product::query()
            ->with(['primaryImage', 'images', 'category', 'brand'])
            ->active();

        $this->applyProductFilters(
            query: $productsQuery,
            request: $request,
            categorySlugs: $selectedCategorySlugs,
            brandSlugs: $selectedBrandSlugs,
            hasInvalidCategoryFilter: $hasInvalidCategoryFilter,
            hasInvalidBrandFilter: $hasInvalidBrandFilter
        );

        match ($request->input('orden', 'destacados')) {
            'precio_asc' => $productsQuery->orderBy('price'),
            'precio_desc' => $productsQuery->orderByDesc('price'),
            'recientes' => $productsQuery->latest('published_at'),
            default => $productsQuery->orderByDesc('is_featured')->latest('published_at'),
        };

        $products = $productsQuery
            ->paginate(9)
            ->withQueryString();

        return view('shop.catalog', compact(
            'brands',
            'categories',
            'products',
            'selectedBrands',
            'selectedBrandSlugs',
            'selectedCategories',
            'selectedCategorySlugs'
        ));
    }

    private function selectedSlugs(Request $request, string $key): array
    {
        return collect($request->input($key, []))
            ->flatten()
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyProductFilters(
        Builder $query,
        Request $request,
        array $categorySlugs = [],
        array $brandSlugs = [],
        bool $hasInvalidCategoryFilter = false,
        bool $hasInvalidBrandFilter = false
    ): Builder {
        $query->search($request->string('q')->toString());

        if ($hasInvalidCategoryFilter || $hasInvalidBrandFilter) {
            $query->whereRaw('1 = 0');
        }

        if ($categorySlugs !== []) {
            $query->whereHas('category', fn (Builder $query) => $query->whereIn('slug', $categorySlugs));
        }

        if ($brandSlugs !== []) {
            $query->whereHas('brand', fn (Builder $query) => $query->whereIn('slug', $brandSlugs));
        }

        if ($request->filled('precio_min')) {
            $query->where('price', '>=', max(0, (float) $request->input('precio_min')));
        }

        if ($request->filled('precio_max')) {
            $query->where('price', '<=', max(0, (float) $request->input('precio_max')));
        }

        if ($request->boolean('oferta')) {
            $query->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price');
        }

        return $query;
    }
}
