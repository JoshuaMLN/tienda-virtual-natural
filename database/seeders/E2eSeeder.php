<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\TaxAffectation;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Delivery\DeliveryService;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use App\Support\Orders\OrderCreationService;
use App\Support\Orders\OrderPaymentService;
use App\Support\Orders\Pricing\OrderPricing;
use App\Support\Orders\Pricing\OrderPricingService;
use App\Support\Orders\Reservations\StockReservationService;
use App\Support\Settings\StorefrontSettings;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class E2eSeeder extends Seeder
{
    private const PENDING_PAYMENT_FIXTURE_RESERVATION_MINUTES = 120;

    public const FISCAL_FIXTURE_CUSTOMER_NAME = 'Pedido fiscal E2E';

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

            $this->seedOrderFixtures(
                $customer,
                $address,
                $district,
                Product::query()->where('sku', 'E2E-OMEGA-3')->sole(),
                Product::query()->where('sku', 'E2E-MAGNESIO')->sole(),
                $admin,
                $timestamp,
            );
        });

        Setting::clearLocalCache();
    }

    private function seedOrderFixtures(
        User $customer,
        CustomerAddress $address,
        DeliveryDistrict $district,
        Product $omega,
        Product $magnesium,
        User $admin,
        DateTimeInterface $timestamp,
    ): void {
        $orders = app(OrderCreationService::class);
        $payments = app(OrderPaymentService::class);
        $pricing = app(OrderPricingService::class);
        $reservations = app(StockReservationService::class);
        $delivery = app(DeliveryService::class);
        $settings = app(StorefrontSettings::class);

        $this->createOrderFixture(
            $orders,
            $payments,
            $pricing,
            $reservations,
            $delivery,
            $settings,
            $customer,
            $address,
            $district,
            [['product' => $magnesium, 'quantity' => 1]],
            DeliveryMethod::HomeDelivery,
            'Domicilio E2E con envio',
            true,
            $timestamp,
        );

        $this->createOrderFixture(
            $orders,
            $payments,
            $pricing,
            $reservations,
            $delivery,
            $settings,
            $customer,
            $address,
            $district,
            [['product' => $omega, 'quantity' => 3]],
            DeliveryMethod::HomeDelivery,
            'Domicilio E2E con envio gratis',
            true,
            $timestamp,
        );

        $this->createOrderFixture(
            $orders,
            $payments,
            $pricing,
            $reservations,
            $delivery,
            $settings,
            $customer,
            $address,
            $district,
            [['product' => $magnesium, 'quantity' => 1]],
            DeliveryMethod::Pickup,
            null,
            true,
            $timestamp,
        );

        $this->createOrderFixture(
            $orders,
            $payments,
            $pricing,
            $reservations,
            $delivery,
            $settings,
            $customer,
            $address,
            $district,
            [['product' => $omega, 'quantity' => 1]],
            DeliveryMethod::HomeDelivery,
            'Domicilio E2E pendiente de pago',
            false,
            $timestamp,
        );

        $fiscalOrder = $this->createOrderFixture(
            $orders,
            $payments,
            $pricing,
            $reservations,
            $delivery,
            $settings,
            $customer,
            $address,
            $district,
            [['product' => $magnesium, 'quantity' => 2]],
            DeliveryMethod::Pickup,
            null,
            true,
            $timestamp,
            self::FISCAL_FIXTURE_CUSTOMER_NAME,
        );

        $this->seedFiscalDocumentFixture($fiscalOrder, $admin, $timestamp);
    }

    /**
     * @param  array<int, array{product: Product, quantity: int}>  $lines
     */
    private function createOrderFixture(
        OrderCreationService $orders,
        OrderPaymentService $payments,
        OrderPricingService $pricing,
        StockReservationService $reservations,
        DeliveryService $delivery,
        StorefrontSettings $settings,
        User $customer,
        CustomerAddress $address,
        DeliveryDistrict $district,
        array $lines,
        DeliveryMethod $method,
        ?string $deliveryReference,
        bool $markPaid,
        DateTimeInterface $timestamp,
        ?string $customerName = null,
    ): Order {
        $basePricing = $pricing->calculate($lines);
        $fulfillment = $this->fulfillment(
            $customer,
            $address,
            $district,
            $basePricing,
            $delivery,
            $settings,
            $method,
            $deliveryReference,
        );
        $finalPricing = $pricing->calculate(
            $lines,
            shippingFeeCents: $fulfillment['shipping_fee_cents'],
        );
        $reservationMinutes = max(5, $settings->stockReservationMinutes());

        if (! $markPaid) {
            $reservationMinutes = max(
                $reservationMinutes,
                self::PENDING_PAYMENT_FIXTURE_RESERVATION_MINUTES,
            );
        }

        $expiration = now()->addMinutes($reservationMinutes);
        $attributes = $this->orderAttributes(
            $customer,
            $address,
            $finalPricing,
            $fulfillment,
            $expiration,
            $settings,
            $customerName,
        );

        if (! $markPaid) {
            $attributes['pending_payment_owner_id'] = $customer->getKey();
        }

        $order = $orders->create(
            $attributes,
            array_map(fn ($line): array => $line->snapshotAttributes(), $finalPricing->lines),
            $timestamp,
        );

        foreach ($order->items as $item) {
            $reservations->reserve(
                $item,
                $expiration,
                operationReference: "e2e:reserve:order:{$order->getKey()}",
            );
        }

        if (! $markPaid) {
            return $order;
        }

        return $payments->markPaid(
            $order,
            reason: 'Fixture E2E de pago confirmado',
            metadata: ['source' => 'e2e-seeder'],
        );
    }

    private function seedFiscalDocumentFixture(Order $order, User $admin, DateTimeInterface $timestamp): void
    {
        $documents = app(FiscalDocumentService::class);
        $originalPath = 'fiscal-documents/e2e/boleta-original.pdf';
        $currentPath = 'fiscal-documents/e2e/boleta-vigente.pdf';

        Storage::disk('local')->put($originalPath, $this->minimalPdf());
        $document = $documents->registerSaleDocument(
            $order,
            FiscalDocumentType::Receipt,
            'B001',
            '90000001',
            $timestamp,
            $originalPath,
            registrar: $admin,
        );
        Storage::disk('local')->put($currentPath, $this->minimalPdf());
        $document = $documents->replacePdf(
            $document,
            $currentPath,
            'Correccion inicial para QA E2E.',
            $admin,
        );
        $documents->correctRegistration($document, [
            'series' => 'B001',
            'correlative' => '90000002',
            'issued_at' => $timestamp,
        ], 'Correlativo inicial corregido para QA E2E.', $admin);
    }

    /**
     * @return array{
     *     shipping_fee_cents: int,
     *     business_days_min: int,
     *     business_days_max: int,
     *     delivery: array<string, string|null>
     * }
     */
    private function fulfillment(
        User $customer,
        CustomerAddress $address,
        DeliveryDistrict $district,
        OrderPricing $pricing,
        DeliveryService $delivery,
        StorefrontSettings $settings,
        DeliveryMethod $method,
        ?string $deliveryReference,
    ): array {
        if ($method === DeliveryMethod::Pickup) {
            return [
                'shipping_fee_cents' => 0,
                'business_days_min' => $settings->pickupPreparationBusinessDaysMin(),
                'business_days_max' => $settings->pickupPreparationBusinessDaysMax(),
                'delivery' => [
                    'delivery_recipient_name' => null,
                    'delivery_phone' => null,
                    'delivery_department' => null,
                    'delivery_province' => null,
                    'delivery_district' => null,
                    'delivery_ubigeo' => null,
                    'delivery_address' => null,
                    'delivery_reference' => null,
                    'pickup_address' => $settings->pickupAddress(),
                ],
            ];
        }

        $quote = $delivery->quoteCents($district->ubigeo, $pricing->productsSubtotalCents);

        if ($quote === null) {
            throw new RuntimeException('No se pudo cotizar el distrito E2E.');
        }

        return [
            'shipping_fee_cents' => $quote->shippingFeeCents,
            'business_days_min' => $quote->businessDaysMin,
            'business_days_max' => $quote->businessDaysMax,
            'delivery' => [
                'delivery_recipient_name' => $address->recipient_name,
                'delivery_phone' => $address->phone,
                'delivery_department' => $address->department,
                'delivery_province' => $address->province,
                'delivery_district' => $address->district,
                'delivery_ubigeo' => $address->ubigeo,
                'delivery_address' => $address->address_line,
                'delivery_reference' => $deliveryReference,
                'pickup_address' => null,
            ],
        ];
    }

    /**
     * @param array{
     *     shipping_fee_cents: int,
     *     business_days_min: int,
     *     business_days_max: int,
     *     delivery: array<string, string|null>
     * } $fulfillment
     * @return array<string, mixed>
     */
    private function orderAttributes(
        User $customer,
        CustomerAddress $address,
        OrderPricing $pricing,
        array $fulfillment,
        DateTimeInterface $expiration,
        StorefrontSettings $settings,
        ?string $customerName,
    ): array {
        return array_merge([
            'user_id' => $customer->getKey(),
            'customer_address_id' => $fulfillment['delivery']['pickup_address'] === null ? $address->getKey() : null,
            'customer_name' => $customerName ?? $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'delivery_method' => $fulfillment['delivery']['pickup_address'] === null
                ? DeliveryMethod::HomeDelivery
                : DeliveryMethod::Pickup,
            ...$fulfillment['delivery'],
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => '12345678',
            'fiscal_first_names' => 'Cliente',
            'fiscal_last_names' => 'E2E',
            'fiscal_business_name' => null,
            'fiscal_address' => null,
            'fiscal_email' => $customer->email,
            'shipping_tax_affectation' => TaxAffectation::Taxed,
            'shipping_tax_rate_bps' => TaxAffectation::Taxed->taxRateBasisPoints(),
            'delivery_business_days_min' => $fulfillment['business_days_min'],
            'delivery_business_days_max' => $fulfillment['business_days_max'],
            'delivery_attempts_per_cycle' => $settings->deliveryAttemptsPerCycle(),
            'delivery_max_automatic_cycles' => $settings->deliveryMaxAutomaticCycles(),
            'reshipment_payment_days' => $settings->reshipmentPaymentDays(),
            'pickup_hold_days' => $settings->pickupHoldDays(),
            'reservation_expires_at' => $expiration,
        ], $pricing->orderAmountAttributes());
    }

    private function requiredPassword(string $key): string
    {
        $password = config($key);

        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException("Falta {$key} en .env.e2e.");
        }

        return $password;
    }

    private function minimalPdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }
}
