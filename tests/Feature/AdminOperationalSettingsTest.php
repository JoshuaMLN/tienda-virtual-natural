<?php

namespace Tests\Feature;

use App\Models\DeliveryDistrict;
use App\Models\NonWorkingDay;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DeliveryDistrictSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearLocalCache();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
        $this->seed(DeliveryDistrictSeeder::class);
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_admin_can_open_and_filter_the_operational_settings_page(): void
    {
        $this->get(route('admin.settings.edit', ['q' => 'San Isidro']))
            ->assertOk()
            ->assertSee('Configuracion operativa')
            ->assertSee('Tarifas por distrito')
            ->assertSee('San Isidro')
            ->assertSee('150131')
            ->assertSee('S/ 8.00')
            ->assertSee('Total')
            ->assertSee('50');
    }

    public function test_district_pagination_is_localized_and_shows_the_current_mobile_page(): void
    {
        $this->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Tiempo para completar el pago')
            ->assertSee('minutos')
            ->assertSee('Pagina <strong>1</strong>', false)
            ->assertSee('de <strong>4</strong>', false)
            ->assertSee('aria-label="Siguiente"', false)
            ->assertDontSee('pagination.previous')
            ->assertDontSee('pagination.next');

        $this->get(route('admin.settings.edit', ['page' => 2]))
            ->assertOk()
            ->assertSee('Pagina <strong>2</strong>', false)
            ->assertSee('aria-label="Anterior"', false);
    }

    public function test_admin_can_update_operational_settings(): void
    {
        $this->patch(route('admin.settings.update'), $this->validSettings([
            'contact_whatsapp' => '+51 999 888 777',
            'contact_email' => 'VENTAS@VITANATURAL.PE',
            'free_shipping_threshold' => '180.50',
            'stock_reservation_minutes' => '20',
            'pickup_address' => 'Av. Camino Real 456, San Isidro, Lima',
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => Setting::CONTACT_WHATSAPP, 'value' => '999888777']);
        $this->assertDatabaseHas('settings', ['key' => Setting::CONTACT_EMAIL, 'value' => 'ventas@vitanatural.pe']);
        $this->assertDatabaseHas('settings', ['key' => Setting::FREE_SHIPPING_THRESHOLD, 'value' => '180.50']);
        $this->assertDatabaseHas('settings', ['key' => Setting::STOCK_RESERVATION_MINUTES, 'value' => '20']);
        $this->assertDatabaseHas('settings', ['key' => Setting::PICKUP_ADDRESS, 'value' => 'Av. Camino Real 456, San Isidro, Lima']);
        $this->assertDatabaseHas('settings', ['key' => Setting::BUSINESS_HOURS_SUNDAY_OPEN, 'value' => '10:00']);
        $this->assertDatabaseHas('settings', ['key' => Setting::BUSINESS_HOURS_SUNDAY_CLOSE, 'value' => '14:00']);
        $this->assertDatabaseHas('settings', ['key' => Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MIN, 'value' => '1']);
        $this->assertDatabaseHas('settings', ['key' => Setting::PICKUP_PREPARATION_BUSINESS_DAYS_MAX, 'value' => '2']);
    }

    public function test_delivery_maximum_cannot_be_lower_than_the_minimum(): void
    {
        $this->from(route('admin.settings.edit'))
            ->patch(route('admin.settings.update'), $this->validSettings([
                'delivery_business_days_min' => '3',
                'delivery_business_days_max' => '2',
            ]))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHasErrors('delivery_business_days_max');
    }

    public function test_partial_weekend_hours_and_an_invalid_pickup_range_are_rejected(): void
    {
        $this->patch(route('admin.settings.update'), $this->validSettings([
            'business_hours_saturday_close' => '',
            'pickup_preparation_business_days_min' => '4',
            'pickup_preparation_business_days_max' => '2',
        ]))
            ->assertSessionHasErrors([
                'business_hours_saturday_close',
                'pickup_preparation_business_days_max',
            ]);
    }

    public function test_admin_can_update_a_district_rate_and_coverage_status(): void
    {
        $district = DeliveryDistrict::query()->where('ubigeo', '150131')->firstOrFail();

        $this->patch(route('admin.settings.districts.update', $district), [
            '_delivery_district_id' => $district->id,
            'shipping_fee' => '9.50',
            'use_default_delivery_window' => '1',
            'is_active' => '0',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_districts', [
            'id' => $district->id,
            'shipping_fee' => '9.50',
            'delivery_business_days_min' => null,
            'delivery_business_days_max' => null,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_assign_and_remove_a_district_specific_delivery_window(): void
    {
        $district = DeliveryDistrict::query()->where('ubigeo', '150131')->firstOrFail();

        $this->patch(route('admin.settings.districts.update', $district), [
            '_delivery_district_id' => $district->id,
            'shipping_fee' => '9.50',
            'use_default_delivery_window' => '0',
            'delivery_business_days_min' => '2',
            'delivery_business_days_max' => '4',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('delivery_districts', [
            'id' => $district->id,
            'delivery_business_days_min' => 2,
            'delivery_business_days_max' => 4,
        ]);

        $this->patch(route('admin.settings.districts.update', $district), [
            '_delivery_district_id' => $district->id,
            'shipping_fee' => '9.50',
            'use_default_delivery_window' => '1',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('delivery_districts', [
            'id' => $district->id,
            'delivery_business_days_min' => null,
            'delivery_business_days_max' => null,
        ]);
    }

    public function test_admin_can_manage_future_non_working_days(): void
    {
        $this->post(route('admin.settings.non-working-days.store'), [
            'date' => now()->addDays(10)->toDateString(),
            'reason' => 'Inventario general',
        ])->assertSessionHasNoErrors();

        $closure = NonWorkingDay::query()->firstOrFail();
        $this->assertSame('Inventario general', $closure->reason);

        $this->post(route('admin.settings.non-working-days.store'), [
            'date' => $closure->date->toDateString(),
        ])->assertSessionHasErrors('date', errorBag: 'nonWorkingDay');

        $this->delete(route('admin.settings.non-working-days.destroy', $closure))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('non_working_days', 0);
    }

    public function test_customer_cannot_access_operational_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $closure = NonWorkingDay::factory()->create();

        $this->get(route('admin.settings.edit'))->assertForbidden();
        $this->patch(route('admin.settings.update'), $this->validSettings())->assertForbidden();
        $this->post(route('admin.settings.non-working-days.store'), [
            'date' => now()->addDay()->toDateString(),
        ])->assertForbidden();
        $this->delete(route('admin.settings.non-working-days.destroy', $closure))->assertForbidden();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'contact_whatsapp' => '987654321',
            'contact_email' => 'hola@vitanatural.pe',
            'contact_phone' => '(01) 123 4567',
            'business_hours_weekdays_open' => '09:00',
            'business_hours_weekdays_close' => '18:00',
            'business_hours_saturday_open' => '09:00',
            'business_hours_saturday_close' => '13:00',
            'business_hours_sunday_open' => '10:00',
            'business_hours_sunday_close' => '14:00',
            'free_shipping_threshold' => '149.00',
            'stock_reservation_minutes' => '15',
            'delivery_business_days_min' => '1',
            'delivery_business_days_max' => '2',
            'pickup_preparation_business_days_min' => '1',
            'pickup_preparation_business_days_max' => '2',
            'pickup_address' => '',
        ], $overrides);
    }
}
