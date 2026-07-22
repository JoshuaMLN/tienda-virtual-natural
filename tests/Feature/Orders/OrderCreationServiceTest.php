<?php

namespace Tests\Feature\Orders;

use App\Enums\DeliveryMethod;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\TaxAffectation;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\User;
use App\Support\Orders\OrderCreationService;
use App\Support\Orders\Pricing\OrderPricing;
use App\Support\Orders\Pricing\OrderPricingService;
use DateTimeImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_number_order_items_snapshots_and_initial_history_atomically(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);

        $order = app(OrderCreationService::class)->create(
            $this->orderAttributes($user, $address, $pricing),
            array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines),
            new DateTimeImmutable('2040-03-15'),
        );

        $this->assertSame('PED-2040-000001', $order->code);
        $this->assertSame(2040, $order->sequence_year);
        $this->assertSame(1, $order->sequence_number);
        $this->assertCount(1, $order->items);
        $this->assertCount(3, $order->statusHistories);
        $this->assertSame(['order', 'payment', 'delivery'], $order->statusHistories->pluck('domain.value')->all());

        $item = $order->items->first();
        $this->assertSame($product->getKey(), $item->product_id);
        $this->assertSame($product->sku, $item->product_sku);
        $this->assertSame($product->name, $item->product_name);
        $this->assertSame(11_800, $item->unit_price_cents);
        $this->assertSame(23_600, $item->gross_total_cents);
        $this->assertSame(20_000, $item->net_value_cents);
        $this->assertSame(3_600, $item->tax_cents);
        $this->assertSame(24_780, $order->total_cents);
        $this->assertSame(21_000, $order->net_value_cents);
        $this->assertSame(3_780, $order->tax_cents);
        $this->assertDatabaseHas('order_sequences', ['year' => 2040, 'last_number' => 1]);
    }

    public function test_product_image_snapshot_prefers_local_path_then_legacy_url_and_finally_default(): void
    {
        $localProduct = Product::factory()->create();
        $localProduct->images()->create([
            'image_path' => 'products/local.webp',
            'url' => '/storage/products/local.webp',
            'is_primary' => true,
        ]);

        $legacyProduct = Product::factory()->create();
        $legacyProduct->images()->create([
            'url' => 'https://images.example.test/legacy.webp',
            'is_primary' => true,
        ]);

        $productWithoutImage = Product::factory()->create();

        $this->assertSame(
            'products/local.webp',
            $this->pricing($localProduct)->lines[0]->snapshotAttributes()['product_image'],
        );
        $this->assertSame(
            'https://images.example.test/legacy.webp',
            $this->pricing($legacyProduct)->lines[0]->snapshotAttributes()['product_image'],
        );
        $this->assertSame(
            Product::DEFAULT_IMAGE,
            $this->pricing($productWithoutImage)->lines[0]->snapshotAttributes()['product_image'],
        );
    }

    public function test_snapshots_remain_after_optional_references_are_deleted(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $order = app(OrderCreationService::class)->create(
            $this->orderAttributes($user, $address, $pricing),
            array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines),
            new DateTimeImmutable('2041-01-01'),
        );
        $item = $order->items->first();
        $customerName = $order->customer_name;
        $deliveryAddress = $order->delivery_address;
        $productName = $item->product_name;
        $productSku = $item->product_sku;

        $address->delete();
        $product->delete();
        $user->delete();

        $order->refresh();
        $item->refresh();

        $this->assertNull($order->user_id);
        $this->assertNull($order->customer_address_id);
        $this->assertNull($item->product_id);
        $this->assertSame($customerName, $order->customer_name);
        $this->assertSame($deliveryAddress, $order->delivery_address);
        $this->assertSame($productName, $item->product_name);
        $this->assertSame($productSku, $item->product_sku);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_status_histories', 3);
    }

    public function test_inconsistent_amounts_are_rejected_before_consuming_a_number(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $attributes = $this->orderAttributes($user, $address, $pricing);
        $attributes['total_cents']++;

        try {
            app(OrderCreationService::class)->create(
                $attributes,
                array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines),
                new DateTimeImmutable('2042-01-01'),
            );
            $this->fail('Expected an inconsistent order to be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('total_cents', $exception->getMessage());
        }

        $this->assertDatabaseMissing('order_sequences', ['year' => 2042]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_database_failure_rolls_back_the_order_and_its_annual_number(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $items = array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines);
        $items[0]['product_id'] = 999_999;

        try {
            app(OrderCreationService::class)->create(
                $this->orderAttributes($user, $address, $pricing),
                $items,
                new DateTimeImmutable('2043-01-01'),
            );
            $this->fail('Expected a foreign-key violation.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseMissing('order_sequences', ['year' => 2043]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_invoice_request_is_stored_as_a_frozen_fiscal_snapshot(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $attributes = array_merge($this->orderAttributes($user, $address, $pricing), [
            'fiscal_document_type' => FiscalDocumentType::Invoice,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Ruc,
            'fiscal_identity_document_number' => '20131312955',
            'fiscal_first_names' => null,
            'fiscal_last_names' => null,
            'fiscal_business_name' => 'Vita Empresa SAC',
            'fiscal_address' => 'Av. Empresa 456, San Isidro',
            'fiscal_email' => 'facturacion@example.test',
        ]);

        $order = app(OrderCreationService::class)->create(
            $attributes,
            array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines),
            new DateTimeImmutable('2044-01-01'),
        );

        $this->assertSame(FiscalDocumentType::Invoice, $order->fiscal_document_type);
        $this->assertSame(FiscalIdentityDocumentType::Ruc, $order->fiscal_identity_document_type);
        $this->assertSame('20131312955', $order->fiscal_identity_document_number);
        $this->assertSame('Vita Empresa SAC', $order->fiscal_business_name);
        $this->assertSame('Av. Empresa 456, San Isidro', $order->fiscal_address);
        $this->assertSame('facturacion@example.test', $order->fiscal_email);
        $this->assertNull($order->saleDocument);
        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_invoice_request_rejects_a_personal_document_without_writing_anything(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $attributes = array_merge($this->orderAttributes($user, $address, $pricing), [
            'fiscal_document_type' => FiscalDocumentType::Invoice,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => '12345678',
            'fiscal_business_name' => 'Empresa invalida',
            'fiscal_address' => 'Direccion invalida',
        ]);

        $this->expectException(DomainException::class);

        try {
            app(OrderCreationService::class)->create(
                $attributes,
                array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines),
                new DateTimeImmutable('2045-01-01'),
            );
        } finally {
            $this->assertDatabaseMissing('order_sequences', ['year' => 2045]);
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_fiscal_policy_rejects_invalid_checksums_and_incompatible_fields(): void
    {
        [$user, $address, $product] = $this->fixtures();
        $pricing = $this->pricing($product);
        $items = array_map(fn ($line): array => $line->snapshotAttributes(), $pricing->lines);
        $base = $this->orderAttributes($user, $address, $pricing);
        $cases = [
            2046 => array_merge($base, [
                'fiscal_document_type' => FiscalDocumentType::Invoice,
                'fiscal_identity_document_type' => FiscalIdentityDocumentType::Ruc,
                'fiscal_identity_document_number' => '20131312954',
                'fiscal_first_names' => null,
                'fiscal_last_names' => null,
                'fiscal_business_name' => 'Empresa SAC',
                'fiscal_address' => 'Av. Empresa 123',
            ]),
            2047 => array_merge($base, [
                'fiscal_business_name' => 'Dato incompatible',
                'fiscal_address' => 'Direccion incompatible',
            ]),
        ];

        foreach ($cases as $year => $attributes) {
            try {
                app(OrderCreationService::class)->create(
                    $attributes,
                    $items,
                    new DateTimeImmutable($year.'-01-01'),
                );
                $this->fail('Expected the invalid fiscal request to be rejected.');
            } catch (DomainException) {
                $this->assertDatabaseMissing('order_sequences', ['year' => $year]);
            }
        }

        $this->assertDatabaseCount('orders', 0);
    }

    /** @return array{User, CustomerAddress, Product} */
    private function fixtures(): array
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->create([
            'recipient_name' => 'Maria Fernanda Perez',
            'phone' => '987654321',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'San Isidro',
            'ubigeo' => '150131',
            'address_line' => 'Av. Camino Real 1234',
            'reference' => 'Frente al parque',
        ]);
        $product = Product::factory()->create([
            'name' => 'Omega 3 Premium',
            'sku' => 'OMEGA-120',
            'short_description' => '120 capsulas',
            'price' => '118.00',
            'tax_affectation' => TaxAffectation::Taxed,
            'stock' => 20,
        ]);

        return [$user, $address, $product];
    }

    private function pricing(Product $product): OrderPricing
    {
        return app(OrderPricingService::class)->calculate(
            [['product' => $product, 'quantity' => 2]],
            shippingFeeCents: 1_180,
        );
    }

    /** @return array<string, mixed> */
    private function orderAttributes(User $user, CustomerAddress $address, OrderPricing $pricing): array
    {
        return array_merge([
            'user_id' => $user->getKey(),
            'customer_address_id' => $address->getKey(),
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'delivery_method' => DeliveryMethod::HomeDelivery,
            'delivery_recipient_name' => $address->recipient_name,
            'delivery_phone' => $address->phone,
            'delivery_department' => $address->department,
            'delivery_province' => $address->province,
            'delivery_district' => $address->district,
            'delivery_ubigeo' => $address->ubigeo,
            'delivery_address' => $address->address_line,
            'delivery_reference' => $address->reference,
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => '12345678',
            'fiscal_first_names' => 'Maria Fernanda',
            'fiscal_last_names' => 'Perez Ruiz',
            'fiscal_email' => $user->email,
            'shipping_tax_affectation' => TaxAffectation::Taxed,
            'shipping_tax_rate_bps' => 1800,
            'delivery_business_days_min' => 1,
            'delivery_business_days_max' => 2,
            'reservation_expires_at' => now()->addMinutes(15),
        ], $pricing->orderAmountAttributes());
    }
}
