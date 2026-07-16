<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULTS = [
        'contact_whatsapp' => '987654321',
        'contact_email' => 'hola@vitanatural.pe',
        'contact_phone' => '(01) 123 4567',
        'business_hours_weekdays' => 'Lunes a viernes: 9:00 am - 6:00 pm',
        'business_hours_saturday' => 'Sabado: 9:00 am - 1:00 pm',
        'free_shipping_threshold' => '149.00',
        'stock_reservation_minutes' => '15',
        'delivery_business_days_min' => '1',
        'delivery_business_days_max' => '2',
        'pickup_address' => '',
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (self::DEFAULTS as $key => $value) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
