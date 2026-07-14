<?php

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductSettingsController as AdminProductSettingsController;
use App\Http\Controllers\Admin\StockController as AdminStockController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('shop.index');
Route::get('/catalogo', CatalogController::class)->name('shop.catalog');
Route::get('/producto/{product:slug}', ProductController::class)->name('shop.product');
Route::get('/carrito', [CartController::class, 'index'])->name('shop.cart');
Route::get('/carrito/info', [CartController::class, 'info'])->name('shop.cart.info');
Route::post('/carrito/items', [CartController::class, 'store'])->name('shop.cart.items.store');
Route::patch('/carrito/items/{product}', [CartController::class, 'update'])->name('shop.cart.items.update');
Route::delete('/carrito/items/{product}', [CartController::class, 'destroy'])->name('shop.cart.items.destroy');
Route::delete('/carrito/warnings', [CartController::class, 'clearWarnings'])->name('shop.cart.warnings.clear');
Route::delete('/carrito', [CartController::class, 'clear'])->name('shop.cart.clear');
Route::view('/contacto', 'shop.contact')->name('shop.contact');
Route::view('/terminos-y-condiciones', 'shop.terms')->name('shop.terms');

Route::prefix('checkout')->name('checkout.')->middleware(['auth', 'verified'])->group(function () {
    Route::view('/', 'checkout.index')->name('index');
    Route::view('/exitoso', 'checkout.success')->name('success');
    Route::view('/fallido', 'checkout.failed')->name('failed');
    Route::view('/pendiente', 'checkout.pending')->name('pending');
});

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('auth.google.callback');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/registro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/registro', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('auth.google.redirect');
    Route::get('/auth/google/confirmar', [GoogleAuthController::class, 'confirm'])
        ->name('auth.google.confirm');
    Route::post('/auth/google/confirmar', [GoogleAuthController::class, 'confirmLink'])
        ->middleware('throttle:5,1')
        ->name('auth.google.confirm.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verificar-correo', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verificar-correo/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/verificar-correo/reenviar', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::prefix('mi-cuenta')->name('account.')->middleware('auth')->group(function () {
    Route::view('/perfil', 'account.profile')->name('profile');
    Route::get('/seguridad', [SecurityController::class, 'edit'])->name('security');
    Route::patch('/seguridad/contrasena', [SecurityController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    Route::post('/seguridad/google', [GoogleAuthController::class, 'link'])
        ->middleware('throttle:6,1')
        ->name('google.link');
    Route::delete('/seguridad/google', [GoogleAuthController::class, 'unlink'])
        ->middleware('throttle:6,1')
        ->name('google.unlink');
    Route::view('/pedidos', 'account.orders')->name('orders');
    Route::view('/direcciones', 'account.addresses')->name('addresses');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

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
    Route::get('/productos/configuracion', [AdminProductSettingsController::class, 'edit'])->name('products.settings.edit');
    Route::patch('/productos/configuracion', [AdminProductSettingsController::class, 'update'])->name('products.settings.update');
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
    Route::get('/stock', [AdminStockController::class, 'index'])->name('stock.index');
    Route::patch('/stock/{product}/alerta', [AdminStockController::class, 'updateThreshold'])->name('stock.threshold.update');
    Route::post('/stock/{product}/movimientos', [AdminStockController::class, 'storeMovement'])->name('stock.movements.store');
    Route::get('/stock/{product}/movimientos', [AdminStockController::class, 'movements'])->name('stock.movements.index');
    Route::view('/banners', 'admin.banners.index')->name('banners.index');
});
