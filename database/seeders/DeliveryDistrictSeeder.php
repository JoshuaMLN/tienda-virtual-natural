<?php

namespace Database\Seeders;

use App\Models\DeliveryDistrict;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Database\Seeder;
use RuntimeException;

class DeliveryDistrictSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = app(LimaCallaoUbigeoCatalog::class);
        $rates = config('delivery.initial_rates', []);

        foreach ($catalog->provinces() as $province) {
            foreach ($catalog->districts($province['code']) as $district) {
                $rate = $rates[$district['code']] ?? null;

                if ($rate === null) {
                    throw new RuntimeException("Falta la tarifa inicial para el UBIGEO {$district['code']}.");
                }

                $location = $catalog->resolve($province['code'], $district['code']);
                $deliveryDistrict = DeliveryDistrict::query()->firstOrNew([
                    'ubigeo' => $location->ubigeo,
                ]);

                $deliveryDistrict->fill([
                    'province_code' => $location->provinceCode,
                    'department' => $location->department,
                    'province' => $location->province,
                    'district' => $location->district,
                ]);

                if (! $deliveryDistrict->exists) {
                    $deliveryDistrict->shipping_fee = $rate;
                    $deliveryDistrict->is_active = true;
                }

                $deliveryDistrict->save();
            }
        }
    }
}
