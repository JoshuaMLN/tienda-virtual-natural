<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSettingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearLocalCache();
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_public_navigation_home_footer_and_contact_use_the_central_settings(): void
    {
        Setting::setValues([
            Setting::CONTACT_WHATSAPP => '999888777',
            Setting::CONTACT_EMAIL => 'soporte@vitanatural.pe',
            Setting::CONTACT_PHONE => '01 555 0101',
            Setting::BUSINESS_HOURS_WEEKDAYS_OPEN => '08:00',
            Setting::BUSINESS_HOURS_WEEKDAYS_CLOSE => '17:00',
            Setting::BUSINESS_HOURS_SATURDAY_OPEN => '',
            Setting::BUSINESS_HOURS_SATURDAY_CLOSE => '',
            Setting::BUSINESS_HOURS_SUNDAY_OPEN => '10:00',
            Setting::BUSINESS_HOURS_SUNDAY_CLOSE => '13:00',
            Setting::FREE_SHIPPING_THRESHOLD => '175.00',
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Envio gratis en Lima y Callao por compras desde S/ 175.00')
            ->assertSee('999 888 777')
            ->assertSee('soporte@vitanatural.pe')
            ->assertSee('Lunes a viernes: 8:00 a. m. - 5:00 p. m.')
            ->assertSee('Domingo: 10:00 a. m. - 1:00 p. m.')
            ->assertDontSee('Envio gratis a todo el Peru');

        $this->get(route('shop.contact'))
            ->assertOk()
            ->assertSee('999 888 777')
            ->assertSee('soporte@vitanatural.pe')
            ->assertSee('01 555 0101')
            ->assertSee('coordina la entrega por WhatsApp');
    }

    public function test_zero_threshold_replaces_the_free_shipping_promise(): void
    {
        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '0');

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Entrega disponible solo en Lima Metropolitana y Callao')
            ->assertSee('Entrega local')
            ->assertDontSee('Por compras desde S/');
    }

    public function test_checkout_layout_uses_the_central_shipping_settings(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($customer);
        app(CartService::class)->add(Product::factory()->create(), 1);

        Setting::setValue(Setting::FREE_SHIPPING_THRESHOLD, '175.00');
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Envio gratis en Lima y Callao por compras desde S/ 175.00');
    }
}
