<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Support\Orders\AdminOrderPresenter;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminOrderPresenter $orders,
    ) {}

    public function __invoke(): View
    {
        $activeProductsCount = Product::query()
            ->where('is_active', true)
            ->count();

        $publishedProductsCount = Product::query()
            ->active()
            ->count();

        $lowStockProducts = Product::query()
            ->with(['primaryImage'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query->where('stock', '>', 0)
                        ->whereColumn('stock', '<=', 'low_stock_threshold');
                })->orWhere('stock', '<=', 0);
            })
            ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->take(4)
            ->get();

        $latestOrders = Order::query()
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->latest('created_at')
            ->latest('id')
            ->take(4)
            ->get()
            ->map(fn (Order $order): array => $this->orders->listItem($order));

        return view('admin.dashboard', compact(
            'activeProductsCount',
            'publishedProductsCount',
            'lowStockProducts',
            'latestOrders',
        ));
    }
}
