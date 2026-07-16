<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
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
            Setting::BUSINESS_HOURS_WEEKDAYS => 'Lunes a viernes: 8:00 am - 5:00 pm',
            Setting::BUSINESS_HOURS_SATURDAY => '',
            Setting::FREE_SHIPPING_THRESHOLD => '175.00',
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Envio gratis en Lima y Callao por compras desde S/ 175.00')
            ->assertSee('999 888 777')
            ->assertSee('soporte@vitanatural.pe')
            ->assertSee('Lunes a viernes: 8:00 am - 5:00 pm')
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

    public function test_checkout_shows_pickup_only_when_an_address_is_configured(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($customer);

        Setting::setValue(Setting::PICKUP_ADDRESS, '');
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('Recojo en tienda');

        Setting::setValue(Setting::PICKUP_ADDRESS, 'Av. Camino Real 456, San Isidro, Lima');
        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Recojo en tienda')
            ->assertSee('Av. Camino Real 456, San Isidro, Lima');
    }
}
