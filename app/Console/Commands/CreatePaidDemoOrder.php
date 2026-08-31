<?php

namespace App\Console\Commands;

use App\Enums\DeliveryMethod;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\TaxAffectation;
use App\Enums\UserRole;
use App\Models\DeliveryDistrict;
use App\Models\Product;
use App\Models\User;
use App\Support\Delivery\DeliveryService;
use App\Support\Money\Money;
use App\Support\Orders\OrderCreationService;
use App\Support\Orders\OrderPaymentService;
use App\Support\Orders\Pricing\OrderPricing;
use App\Support\Orders\Pricing\OrderPricingService;
use App\Support\Orders\Reservations\StockReservationService;
use App\Support\Settings\StorefrontSettings;
use DateTimeInterface;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CreatePaidDemoOrder extends Command
{
    private const DEFAULT_EMAIL = 'cliente.demo@vitanatural.test';

    private const DEFAULT_PASSWORD = 'Demo12345!';

    protected $signature = 'demo:create-paid-order
        {--email=cliente.demo@vitanatural.test : Correo de una cuenta cliente existente o del cliente demo por crear}
        {--method=random : Modalidad: random, home o pickup}
        {--items=1 : Cantidad de productos distintos, entre 1 y 5}';

    protected $description = 'Crea un pedido pagado coherente para pruebas locales de los flujos posteriores al pago';

    public function handle(
        OrderCreationService $orders,
        OrderPricingService $pricing,
        StockReservationService $reservations,
        OrderPaymentService $payments,
        DeliveryService $delivery,
        StorefrontSettings $settings,
    ): int {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo puede ejecutarse en entornos local o testing.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->option('email')));
        $itemCount = filter_var($this->option('items'), FILTER_VALIDATE_INT);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('El correo indicado no es valido.');

            return self::FAILURE;
        }

        if ($itemCount === false || $itemCount < 1 || $itemCount > 5) {
            $this->error('La cantidad de productos debe ser un entero entre 1 y 5.');

            return self::FAILURE;
        }

        try {
            [$order, $createdCustomer] = DB::transaction(function () use (
                $email,
                $itemCount,
                $orders,
                $pricing,
                $reservations,
                $payments,
                $delivery,
                $settings,
            ): array {
                $products = $this->products($itemCount);
                [$customer, $createdCustomer] = $this->customer($email);
                $lineInputs = $products
                    ->map(fn (Product $product): array => [
                        'product' => $product,
                        'quantity' => random_int(1, min(3, $product->stock)),
                    ])
                    ->all();
                $basePricing = $pricing->calculate($lineInputs);
                $fulfillment = $this->fulfillment(
                    $customer,
                    $basePricing,
                    $delivery,
                    $settings,
                );
                $finalPricing = $pricing->calculate(
                    $lineInputs,
                    shippingFeeCents: $fulfillment['shipping_fee_cents'],
                );
                $expiration = now()->addMinutes(min(1_440, max(5, $settings->stockReservationMinutes())));
                $order = $orders->create(
                    $this->orderAttributes(
                        $customer,
                        $finalPricing,
                        $fulfillment,
                        $expiration,
                        $settings,
                    ),
                    array_map(
                        fn ($line): array => $line->snapshotAttributes(),
                        $finalPricing->lines,
                    ),
                );

                foreach ($order->items as $item) {
                    $reservations->reserve(
                        $item,
                        $expiration,
                        operationReference: "demo:reserve:order:{$order->getKey()}",
                    );
                }

                $order = $payments->markPaid(
                    $order,
                    reason: 'Pago de demostracion generado por Artisan',
                    metadata: [
                        'source' => 'artisan',
                        'command' => $this->getName(),
                    ],
                );

                return [
                    $order->load(['items', 'stockReservations']),
                    $createdCustomer,
                ];
            }, 5);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Pedido pagado {$order->code} creado correctamente.");
        $this->table(
            ['Producto', 'Cantidad', 'Subtotal'],
            $order->items->map(fn ($item): array => [
                $item->product_name,
                $item->quantity,
                Money::fromCents($item->total_cents)->formatted(),
            ])->all(),
        );
        $this->line("Modalidad: {$order->delivery_method->label()}");
        $this->line('Total: '.Money::fromCents($order->total_cents)->formatted());
        $this->line("Cliente: {$order->customer_email}");

        if ($createdCustomer) {
            $this->warn('Se creo una cuenta cliente local para la prueba.');
            $this->line('Contrasena: '.self::DEFAULT_PASSWORD);
        } elseif ($email === self::DEFAULT_EMAIL) {
            $this->comment('La cuenta demo ya existia; se conservo su contrasena actual.');
        }

        $this->newLine();
        $this->line('Cliente: '.route('account.orders.show', $order->code));
        $this->line('Administrador: '.route('admin.orders.show', $order->code));
        $this->warn('La reserva consumio stock real de esta base de datos local.');

        return self::SUCCESS;
    }

    /** @return Collection<int, Product> */
    private function products(int $itemCount): Collection
    {
        $productIds = Product::query()
            ->active()
            ->where('stock', '>', 0)
            ->inRandomOrder()
            ->limit($itemCount)
            ->pluck('id');

        if ($productIds->count() !== $itemCount) {
            $productLabel = $itemCount === 1 ? 'producto visible' : 'productos visibles';

            throw new DomainException("Se necesitan {$itemCount} {$productLabel} y con stock para crear el pedido.");
        }

        return Product::query()
            ->with('primaryImage')
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @return array{User, bool} */
    private function customer(string $email): array
    {
        $customer = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->lockForUpdate()
            ->first();

        if ($customer !== null && $customer->role !== UserRole::Customer) {
            throw new DomainException('El correo indicado pertenece a una cuenta administrativa.');
        }

        if ($customer !== null) {
            return [$customer, false];
        }

        $customer = new User([
            'name' => 'Cliente Demo',
            'email' => $email,
            'phone' => '912345678',
            'password' => self::DEFAULT_PASSWORD,
            'terms_accepted_at' => now(),
        ]);
        $customer->forceFill([
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
        ])->save();

        return [$customer, true];
    }

    /**
     * @return array{
     *     method: DeliveryMethod,
     *     shipping_fee_cents: int,
     *     business_days_min: int,
     *     business_days_max: int,
     *     delivery: array<string, string|null>
     * }
     */
    private function fulfillment(
        User $customer,
        OrderPricing $pricing,
        DeliveryService $delivery,
        StorefrontSettings $settings,
    ): array {
        $requestedMethod = Str::lower(trim((string) $this->option('method')));

        if (! in_array($requestedMethod, ['random', 'home', 'pickup'], true)) {
            throw new DomainException('La modalidad debe ser random, home o pickup.');
        }

        $homeAvailable = DeliveryDistrict::query()->active()->exists();
        $pickupAvailable = $settings->pickupEnabled();
        $method = match ($requestedMethod) {
            'home' => DeliveryMethod::HomeDelivery,
            'pickup' => DeliveryMethod::Pickup,
            default => $this->randomMethod($homeAvailable, $pickupAvailable),
        };

        if ($method === DeliveryMethod::HomeDelivery) {
            if (! $homeAvailable) {
                throw new DomainException('No hay distritos activos para crear una entrega a domicilio.');
            }

            $district = DeliveryDistrict::query()
                ->active()
                ->inRandomOrder()
                ->lockForUpdate()
                ->firstOrFail();
            $quote = $delivery->quoteCents(
                $district->ubigeo,
                $pricing->productsSubtotalCents,
                lockForUpdate: true,
            );

            if ($quote === null) {
                throw new DomainException('No se pudo cotizar el distrito elegido para la demostracion.');
            }

            return [
                'method' => $method,
                'shipping_fee_cents' => $quote->shippingFeeCents,
                'business_days_min' => $quote->businessDaysMin,
                'business_days_max' => $quote->businessDaysMax,
                'delivery' => [
                    'delivery_recipient_name' => $customer->name,
                    'delivery_phone' => $customer->phone ?: '912345678',
                    'delivery_department' => $district->department,
                    'delivery_province' => $district->province,
                    'delivery_district' => $district->district,
                    'delivery_ubigeo' => $district->ubigeo,
                    'delivery_address' => 'Av. Prueba 123',
                    'delivery_reference' => 'Pedido generado para validacion local',
                    'pickup_address' => null,
                ],
            ];
        }

        if (! $pickupAvailable) {
            throw new DomainException('Configura una direccion de recojo antes de usar la modalidad pickup.');
        }

        return [
            'method' => $method,
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

    private function randomMethod(bool $homeAvailable, bool $pickupAvailable): DeliveryMethod
    {
        $available = [];

        if ($homeAvailable) {
            $available[] = DeliveryMethod::HomeDelivery;
        }

        if ($pickupAvailable) {
            $available[] = DeliveryMethod::Pickup;
        }

        if ($available === []) {
            throw new DomainException('Configura al menos un distrito activo o una direccion de recojo.');
        }

        return $available[array_rand($available)];
    }

    /**
     * @param array{
     *     method: DeliveryMethod,
     *     shipping_fee_cents: int,
     *     business_days_min: int,
     *     business_days_max: int,
     *     delivery: array<string, string|null>
     * } $fulfillment
     * @return array<string, mixed>
     */
    private function orderAttributes(
        User $customer,
        OrderPricing $pricing,
        array $fulfillment,
        DateTimeInterface $expiration,
        StorefrontSettings $settings,
    ): array {
        return array_merge([
            'user_id' => $customer->getKey(),
            'customer_address_id' => null,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?: '912345678',
            'delivery_method' => $fulfillment['method'],
            ...$fulfillment['delivery'],
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => '12345678',
            'fiscal_first_names' => 'Cliente',
            'fiscal_last_names' => 'Demo',
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
}
