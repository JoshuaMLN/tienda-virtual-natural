<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryMovementRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Support\Inventory\InsufficientStockException;
use App\Support\Inventory\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['brand', 'category', 'primaryImage'])
            ->withCount('inventoryMovements')
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function (Builder $query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('categoria'), function (Builder $query) use ($request) {
                $query->where('category_id', $request->integer('categoria'));
            })
            ->when($request->filled('marca'), function (Builder $query) use ($request) {
                $query->where('brand_id', $request->integer('marca'));
            });

        $this->applyStockStatusFilter($products, $request->string('estado_stock')->toString());

        $products = $products
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock.index', [
            'brands' => $this->brandOptions(),
            'categories' => $this->categoryOptions(),
            'products' => $products,
            'summary' => $this->summary(),
        ]);
    }

    public function movements(Product $product): View
    {
        $product->load(['brand', 'category', 'primaryImage']);

        $movements = $product->inventoryMovements()
            ->with('createdBy')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stock.movements', compact('movements', 'product'));
    }

    public function updateThreshold(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validateWithBag(
            "threshold_{$product->id}",
            [
                'low_stock_threshold' => ['required', 'integer', 'min:0'],
            ],
            [
                'low_stock_threshold.required' => 'Ingresa el stock minimo de alerta.',
                'low_stock_threshold.integer' => 'El stock minimo de alerta debe ser un numero entero.',
                'low_stock_threshold.min' => 'El stock minimo de alerta no puede ser negativo.',
            ],
            [
                'low_stock_threshold' => 'stock minimo de alerta',
            ]
        );

        $product->update([
            'low_stock_threshold' => (int) $validated['low_stock_threshold'],
        ]);

        return back()->with('success', "Alerta de stock de {$product->name} actualizada.");
    }

    public function storeMovement(
        StoreInventoryMovementRequest $request,
        Product $product,
        InventoryService $inventoryService
    ): RedirectResponse {
        $validated = $request->validated();
        $data = [
            'reason' => $validated['reason'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ];

        try {
            $movement = match ($validated['type']) {
                InventoryMovement::TYPE_IN => $inventoryService->increase($product, (int) $validated['quantity'], $data),
                InventoryMovement::TYPE_OUT => $inventoryService->decrease($product, (int) $validated['quantity'], $data),
                InventoryMovement::TYPE_ADJUSTMENT => $inventoryService->adjust($product, (int) $validated['new_stock'], $data),
            };
        } catch (InsufficientStockException $exception) {
            return back()
                ->withErrors([
                    'quantity' => "No hay stock suficiente. Stock disponible: {$exception->availableStock}.",
                ], 'movement')
                ->withInput();
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withErrors(['quantity' => $exception->getMessage()], 'movement')
                ->withInput();
        }

        return back()->with('success', "Movimiento registrado. Stock actual de {$product->name}: {$movement->stock_after}.");
    }

    private function applyStockStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'sin-stock' => $query->where('stock', '<=', 0),
            'bajo-stock' => $query
                ->where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'low_stock_threshold'),
            'optimo' => $query
                ->where('stock', '>', 0)
                ->where(function (Builder $query) {
                    $query->whereColumn('stock', '>', 'low_stock_threshold')
                        ->orWhere('low_stock_threshold', '<=', 0);
                }),
            default => null,
        };
    }

    private function summary(): array
    {
        return [
            'products' => Product::query()->count(),
            'stock_units' => (int) Product::query()->sum('stock'),
            'low_stock' => Product::query()
                ->where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->count(),
            'out_of_stock' => Product::query()
                ->where('stock', '<=', 0)
                ->count(),
        ];
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
}
