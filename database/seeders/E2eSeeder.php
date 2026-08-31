<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment() !== 'e2e') {
            throw new RuntimeException('E2eSeeder solo puede ejecutarse con APP_ENV=e2e.');
        }

        $customerPassword = $this->requiredPassword('e2e.customer.password');
        $adminPassword = $this->requiredPassword('e2e.admin.password');
        $timestamp = CarbonImmutable::parse('2026-01-01 09:00:00', 'America/Lima');

        DB::transaction(function () use ($adminPassword, $customerPassword, $timestamp): void {
            Setting::setValues([
                Setting::PUBLIC_STOCK_DISPLAY_THRESHOLD => '10',
                Setting::FREE_SHIPPING_THRESHOLD => '149.00',
                Setting::STOCK_RESERVATION_MINUTES => '15',
                Setting::PICKUP_ADDRESS => 'Av. Javier Prado Este 1234, San Isidro, Lima',
            ]);

            $customer = new User;
            $customer->forceFill([
                'name' => 'Cliente E2E',
                'email' => config('e2e.customer.email'),
                'password' => $customerPassword,
                'phone' => '999888777',
                'role' => UserRole::Customer,
                'email_verified_at' => $timestamp,
                'terms_accepted_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();

            $admin = new User;
            $admin->forceFill([
                'name' => 'Administradora E2E',
                'email' => config('e2e.admin.email'),
                'password' => $adminPassword,
                'phone' => '988777666',
                'role' => UserRole::Admin,
                'email_verified_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();

            $district = DeliveryDistrict::query()->create([
                'ubigeo' => '150131',
                'province_code' => '1501',
                'department' => 'Lima',
                'province' => 'Lima',
                'district' => 'San Isidro',
                'shipping_fee' => '12.00',
                'delivery_business_days_min' => 1,
                'delivery_business_days_max' => 2,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $address = new CustomerAddress([
                'label' => 'Casa E2E',
                'recipient_name' => 'Cliente E2E',
                'phone' => '999888777',
                'department' => $district->department,
                'province' => $district->province,
                'district' => $district->district,
                'ubigeo' => $district->ubigeo,
                'address_line' => 'Av. Javier Prado Este 1234, dpto. 401',
                'reference' => 'Frente al parque',
                'is_default' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
            $address->user()->associate($customer);
            $address->save();

            $category = Category::query()->create([
                'name' => 'Suplementos E2E',
                'slug' => 'e2e-suplementos',
                'description' => 'Categoria determinista para Playwright.',
                'icon_class' => 'bi-capsule-pill',
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $brand = Brand::query()->create([
                'name' => 'Marca E2E',
                'slug' => 'marca-e2e',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            foreach ([
                ['Omega 3 E2E', 'omega-3-e2e', 'E2E-OMEGA-3', '49.90', 20, true],
                ['Magnesio E2E', 'magnesio-e2e', 'E2E-MAGNESIO', '39.90', 12, false],
            ] as [$name, $slug, $sku, $price, $stock, $featured]) {
                Product::query()->create([
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'name' => $name,
                    'slug' => $slug,
                    'sku' => $sku,
                    'short_description' => 'Producto determinista para pruebas E2E.',
                    'description' => 'Producto sin imagen remota para la suite Playwright.',
                    'price' => $price,
                    'tax_affectation' => 'taxed',
                    'stock' => $stock,
                    'low_stock_threshold' => 5,
                    'rating_average' => '0.00',
                    'reviews_count' => 0,
                    'is_active' => true,
                    'is_featured' => $featured,
                    'published_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });

        Setting::clearLocalCache();
    }

    private function requiredPassword(string $key): string
    {
        $password = config($key);

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException("Falta {$key} en .env.e2e.");
        }

        return $password;
    }
}
