<?php

namespace App\Providers;

use App\Models\Category;
use App\Support\Cart\CartMergeCoordinator;
use App\Support\Cart\CartService;
use App\Support\Cart\CartStorageInterface;
use App\Support\Cart\CartStorageResolver;
use App\Support\Notifications\AdminNotificationService;
use App\Support\Notifications\Providers\FulfillmentAlertNotificationProvider;
use App\Support\Notifications\Providers\StockAlertNotificationProvider;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CartMergeCoordinator::class);
        $this->app->bind(
            CartStorageInterface::class,
            fn ($app): CartStorageInterface => $app->make(CartStorageResolver::class)->resolve()
        );

        $this->app->singleton(AdminNotificationService::class, function ($app) {
            $service = new AdminNotificationService;
            $service->registerProvider(StockAlertNotificationProvider::class);
            $service->registerProvider(FulfillmentAlertNotificationProvider::class);

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

        Auth::resolved(function (AuthManager $auth): void {
            $guard = $auth->guard('web');

            if ($guard instanceof SessionGuard) {
                $rememberDays = max(1, (int) config('auth.remember.days', 30));
                $guard->setRememberDuration($rememberDays * 24 * 60);
            }
        });

        View::composer('components.shop.navbar', function ($view) {
            $view->with('navigationCategories', Category::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get());
            $view->with('cartCount', Auth::user()?->isAdmin()
                ? 0
                : app(CartService::class)->count());
            $view->with('storeSettings', app(StorefrontSettings::class));
        });

        View::composer([
            'components.shop.footer',
            'components.shop.trust-badges',
            'components.checkout.shipping-form',
            'layouts.checkout',
            'shop.contact',
            'shop.index',
        ], function ($view) {
            $view->with('storeSettings', app(StorefrontSettings::class));
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
