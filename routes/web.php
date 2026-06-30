<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'shop.index')->name('shop.index');
Route::view('/catalogo', 'shop.catalog')->name('shop.catalog');
Route::view('/producto/omega-3-premium', 'shop.product-detail')->name('shop.product');
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
    Route::view('/productos', 'admin.products.index')->name('products.index');
    Route::view('/categorias', 'admin.categories.index')->name('categories.index');
    Route::view('/pedidos', 'admin.orders.index')->name('orders.index');
    Route::view('/pedidos/vn-2024-000123', 'admin.orders.show')->name('orders.show');
    Route::view('/pagos', 'admin.payments.index')->name('payments.index');
    Route::view('/stock', 'admin.stock.index')->name('stock.index');
    Route::view('/banners', 'admin.banners.index')->name('banners.index');
});
