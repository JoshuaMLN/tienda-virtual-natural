<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __invoke(Product $product): View
    {
        $product->load(['category', 'brand', 'images', 'primaryImage']);

        abort_unless(Product::query()->active()->whereKey($product->id)->exists(), 404);

        $relatedProducts = Product::query()
            ->with(['primaryImage', 'images', 'category', 'brand'])
            ->active()
            ->whereKeyNot($product->id)
            ->where('category_id', $product->category_id)
            ->latest('published_at')
            ->take(4)
            ->get();

        return view('shop.product-detail', compact('product', 'relatedProducts'));
    }
}
