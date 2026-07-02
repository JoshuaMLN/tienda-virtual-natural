<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;

Route::get('/', HomeController::class)->name('shop.index');
Route::get('/catalogo', CatalogController::class)->name('shop.catalog');
Route::get('/producto/{product:slug}', ProductController::class)->name('shop.product');
Route::view('/carrito', 'shop.cart')->name('shop.cart');
Route::view('/contacto', 'shop.contact')->name('shop.contact');
Route::view('/terminos-y-condiciones', 'shop.terms')->name('shop.terms');

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::view('/', 'checkout.index')->name('index');
    Route::view('/exitoso', 'checkout.success')->name('success');
    Route::view('/fallido', 'checkout.failed')->name('failed');
    Route::view('/pendiente', 'checkout.pending')->name('pending');
});

Route::view('/login', 'auth.login')->name('login');
Route::view('/registro', 'auth.register')->name('register');
Route::view('/recuperar-contrasena', 'auth.forgot-password')->name('password.request');

Route::prefix('mi-cuenta')->name('account.')->group(function () {
    Route::view('/perfil', 'account.profile')->name('profile');
    Route::view('/pedidos', 'account.orders')->name('orders');
    Route::view('/direcciones', 'account.addresses')->name('addresses');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.dashboard')->name('dashboard');

    Route::get('/categorias/slug-sugerido', [AdminCategoryController::class, 'suggestSlug'])->name('categories.suggest-slug');
    Route::patch('/categorias/{category}/estado', [AdminCategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
    Route::resource('/categorias', AdminCategoryController::class)
        ->except('show')
        ->parameters(['categorias' => 'category'])
        ->names([
            'index' => 'categories.index',
            'create' => 'categories.create',
            'store' => 'categories.store',
            'edit' => 'categories.edit',
            'update' => 'categories.update',
            'destroy' => 'categories.destroy',
        ]);

    Route::patch('/marcas/{brand}/estado', [AdminBrandController::class, 'toggleStatus'])->name('brands.toggle-status');
    Route::get('/marcas/slug-sugerido', [AdminBrandController::class, 'suggestSlug'])->name('brands.suggest-slug');
    Route::resource('/marcas', AdminBrandController::class)
        ->except('show')
        ->parameters(['marcas' => 'brand'])
        ->names([
            'index' => 'brands.index',
            'create' => 'brands.create',
            'store' => 'brands.store',
            'edit' => 'brands.edit',
            'update' => 'brands.update',
            'destroy' => 'brands.destroy',
        ]);

    Route::patch('/productos/{product}/estado', [AdminProductController::class, 'toggleStatus'])->name('products.toggle-status');
    Route::patch('/productos/{product}/publicacion', [AdminProductController::class, 'togglePublication'])->name('products.toggle-publication');
    Route::get('/productos/slug-sugerido', [AdminProductController::class, 'suggestSlug'])->name('products.suggest-slug');
    Route::patch('/productos/{product}/imagen-principal', [AdminProductController::class, 'updateMainImage'])->name('products.main-image.update');
    Route::post('/productos/{product}/imagenes', [AdminProductController::class, 'storeImage'])->name('products.images.store');
    Route::delete('/productos/{product}/imagenes/{image}', [AdminProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::patch('/productos/{product}/imagenes/{image}/principal', [AdminProductController::class, 'makePrimaryImage'])->name('products.images.primary');
    Route::resource('/productos', AdminProductController::class)
        ->except('show')
        ->parameters(['productos' => 'product'])
        ->names([
            'index' => 'products.index',
            'create' => 'products.create',
            'store' => 'products.store',
            'edit' => 'products.edit',
            'update' => 'products.update',
            'destroy' => 'products.destroy',
        ]);

    Route::view('/pedidos', 'admin.orders.index')->name('orders.index');
    Route::view('/pedidos/vn-2024-000123', 'admin.orders.show')->name('orders.show');
    Route::view('/pagos', 'admin.payments.index')->name('payments.index');
    Route::view('/stock', 'admin.stock.index')->name('stock.index');
    Route::view('/banners', 'admin.banners.index')->name('banners.index');
});
