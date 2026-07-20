<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_districts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('delivery_business_days_min')->nullable()->after('shipping_fee');
            $table->unsignedTinyInteger('delivery_business_days_max')->nullable()->after('delivery_business_days_min');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('delivery_estimated_from')->nullable()->after('delivery_window_starts_at');
            $table->date('delivery_estimated_to')->nullable()->after('delivery_estimated_from');
        });

        Schema::create('non_working_days', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 120)->nullable();
            $table->timestamps();
        });

        [$weekdayOpen, $weekdayClose] = $this->hoursFromLegacySetting(
            'business_hours_weekdays',
            ['09:00', '18:00'],
        );
        [$saturdayOpen, $saturdayClose] = $this->hoursFromLegacySetting(
            'business_hours_saturday',
            ['09:00', '13:00'],
            true,
        );

        $this->insertSettings([
            'business_hours_weekdays_open' => $weekdayOpen,
            'business_hours_weekdays_close' => $weekdayClose,
            'business_hours_saturday_open' => $saturdayOpen,
            'business_hours_saturday_close' => $saturdayClose,
            'business_hours_sunday_open' => '',
            'business_hours_sunday_close' => '',
            'pickup_preparation_business_days_min' => '1',
            'pickup_preparation_business_days_max' => '1',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'business_hours_weekdays_open',
            'business_hours_weekdays_close',
            'business_hours_saturday_open',
            'business_hours_saturday_close',
            'business_hours_sunday_open',
            'business_hours_sunday_close',
            'pickup_preparation_business_days_min',
            'pickup_preparation_business_days_max',
        ])->delete();

        Schema::dropIfExists('non_working_days');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['delivery_estimated_from', 'delivery_estimated_to']);
        });

        Schema::table('delivery_districts', function (Blueprint $table): void {
            $table->dropColumn(['delivery_business_days_min', 'delivery_business_days_max']);
        });
    }

    /**
     * @param  array{0: string, 1: string}  $fallback
     * @return array{0: string, 1: string}
     */
    private function hoursFromLegacySetting(string $key, array $fallback, bool $blankDisables = false): array
    {
        $value = trim((string) DB::table('settings')->where('key', $key)->value('value'));

        if ($value === '' && $blankDisables) {
            return ['', ''];
        }

        preg_match_all(
            '/(\d{1,2}):(\d{2})\s*(a\.?\s*m\.?|p\.?\s*m\.?)/i',
            $value,
            $matches,
            PREG_SET_ORDER,
        );

        if (count($matches) === 2) {
            return [
                $this->toTwentyFourHour($matches[0]),
                $this->toTwentyFourHour($matches[1]),
            ];
        }

        preg_match_all('/\b([01]?\d|2[0-3]):([0-5]\d)\b/', $value, $matches, PREG_SET_ORDER);

        if (count($matches) !== 2) {
            return $fallback;
        }

        return array_map(
            fn (array $match): string => sprintf('%02d:%02d', (int) $match[1], (int) $match[2]),
            $matches,
        );
    }

    /** @param array<int, string> $match */
    private function toTwentyFourHour(array $match): string
    {
        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $period = strtolower(trim($match[3]));

        if (str_starts_with($period, 'p') && $hour < 12) {
            $hour += 12;
        } elseif (str_starts_with($period, 'a') && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /** @param array<string, string> $settings */
    private function insertSettings(array $settings): void
    {
        $now = now();
        $rows = [];

        foreach ($settings as $key => $value) {
            $rows[] = [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('settings')->insertOrIgnore($rows);
    }
};
