<?php

namespace Tests\Feature;

use App\Models\DeliveryDistrict;
use App\Models\Setting;
use App\Support\Delivery\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_it_quotes_an_active_district_and_applies_the_free_shipping_threshold(): void
    {
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150131',
            'district' => 'San Isidro',
            'shipping_fee' => '8.00',
        ]);
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '149.00');

        $paidQuote = $this->service()->quote('150131', '148.99');
        $freeQuote = $this->service()->quote('150131', '149.00');

        $this->assertNotNull($paidQuote);
        $this->assertSame(800, $paidQuote->baseFeeCents);
        $this->assertSame(800, $paidQuote->shippingFeeCents);
        $this->assertFalse($paidQuote->hasFreeShipping);
        $this->assertSame(1, $paidQuote->businessDaysMin);
        $this->assertSame(2, $paidQuote->businessDaysMax);
        $this->assertNotNull($freeQuote);
        $this->assertSame(0, $freeQuote->shippingFeeCents);
        $this->assertTrue($freeQuote->hasFreeShipping);
    }

    public function test_zero_disables_free_shipping_even_for_a_large_subtotal(): void
    {
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150131',
            'shipping_fee' => '8.00',
        ]);
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '0');

        $quote = $this->service()->quote('150131', '9999.00');

        $this->assertNotNull($quote);
        $this->assertSame(800, $quote->shippingFeeCents);
        $this->assertFalse($quote->hasFreeShipping);
    }

    public function test_an_active_district_can_have_a_zero_base_fee_without_marking_threshold_shipping(): void
    {
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150140',
            'district' => 'Santiago de Surco',
            'shipping_fee' => '0.00',
        ]);
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '149.00');

        $quote = $this->service()->quote('150140', '10.00');

        $this->assertNotNull($quote);
        $this->assertSame(0, $quote->baseFeeCents);
        $this->assertSame(0, $quote->shippingFeeCents);
        $this->assertFalse($quote->hasFreeShipping);
    }

    public function test_a_district_window_overrides_the_global_delivery_window(): void
    {
        DeliveryDistrict::factory()->create([
            'ubigeo' => '150131',
            'delivery_business_days_min' => 3,
            'delivery_business_days_max' => 5,
        ]);
        Setting::setValue(Setting::DELIVERY_BUSINESS_DAYS_MIN, 1);
        Setting::setValue(Setting::DELIVERY_BUSINESS_DAYS_MAX, 2);

        $quote = $this->service()->quote('150131', '100.00');

        $this->assertNotNull($quote);
        $this->assertSame(3, $quote->businessDaysMin);
        $this->assertSame(5, $quote->businessDaysMax);
    }

    public function test_inactive_or_unknown_districts_are_outside_coverage(): void
    {
        DeliveryDistrict::factory()->inactive()->create([
            'ubigeo' => '150131',
        ]);

        $this->assertFalse($this->service()->hasCoverage('150131'));
        $this->assertNull($this->service()->quote('150131', '100.00'));
        $this->assertFalse($this->service()->hasCoverage('999999'));
    }

    public function test_pickup_is_available_only_when_its_store_address_is_configured(): void
    {
        Setting::setValue(Setting::PICKUP_ADDRESS, '');
        $this->assertFalse($this->service()->pickupAvailable());

        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Javier Prado 1234, San Isidro');
        $this->assertTrue($this->service()->pickupAvailable());
    }

    private function service(): DeliveryService
    {
        return app(DeliveryService::class);
    }
}
