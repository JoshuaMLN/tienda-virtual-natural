<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->active()
            ->featured()
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $featuredProducts = Product::query()
            ->with(['primaryImage', 'images', 'category', 'brand'])
            ->active()
            ->featured()
            ->latest('published_at')
            ->take(6)
            ->get();

        $brands = Brand::query()
            ->active()
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        return view('shop.index', compact('categories', 'featuredProducts', 'brands'));
    }
}
