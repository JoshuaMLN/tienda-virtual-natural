<?php

namespace App\Providers;

use App\Models\Category;
use App\Support\Cart\CartStorageInterface;
use App\Support\Cart\CartService;
use App\Support\Cart\SessionCartStorage;
use App\Support\Notifications\AdminNotificationService;
use App\Support\Notifications\Providers\StockAlertNotificationProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartStorageInterface::class, SessionCartStorage::class);

        $this->app->singleton(AdminNotificationService::class, function ($app) {
            $service = new AdminNotificationService();
            $service->registerProvider(StockAlertNotificationProvider::class);
            // Future providers will be registered here
            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('components.shop.navbar', function ($view) {
            $view->with('navigationCategories', Category::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get());
            $view->with('cartCount', app(CartService::class)->count());
        });

        View::composer('components.shop.cart-drawer', function ($view) {
            $view->with('cartCount', app(CartService::class)->count());
        });

        View::composer('components.admin.topbar', function ($view) {
            $service = app(AdminNotificationService::class);
            $notifications = $service->getAll();

            $view->with('adminNotifications', $notifications);
            $view->with('adminNotificationCount', count($notifications));
        });
    }
}
