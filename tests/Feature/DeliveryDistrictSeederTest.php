<?php

namespace Tests\Feature;

use App\Models\DeliveryDistrict;
use Database\Seeders\DeliveryDistrictSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryDistrictSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_the_50_lima_and_callao_districts_with_initial_rates(): void
    {
        $this->seed(DeliveryDistrictSeeder::class);

        $this->assertDatabaseCount('delivery_districts', 50);
        $this->assertSame(43, DeliveryDistrict::query()->where('province_code', '1501')->count());
        $this->assertSame(7, DeliveryDistrict::query()->where('province_code', '0701')->count());
        $this->assertSame(50, DeliveryDistrict::query()->active()->count());

        $this->assertDatabaseHas('delivery_districts', [
            'ubigeo' => '150131',
            'district' => 'San Isidro',
            'shipping_fee' => '8.00',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('delivery_districts', [
            'ubigeo' => '070107',
            'district' => 'Mi Perú',
            'shipping_fee' => '22.00',
            'is_active' => true,
        ]);
    }

    public function test_seeder_preserves_rates_and_status_previously_edited_by_an_admin(): void
    {
        $this->seed(DeliveryDistrictSeeder::class);

        DeliveryDistrict::query()->where('ubigeo', '150131')->update([
            'shipping_fee' => '9.50',
            'is_active' => false,
        ]);

        $this->seed(DeliveryDistrictSeeder::class);

        $this->assertDatabaseHas('delivery_districts', [
            'ubigeo' => '150131',
            'shipping_fee' => '9.50',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('delivery_districts', 50);
    }
}
