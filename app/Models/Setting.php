<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    public const PUBLIC_STOCK_DISPLAY_THRESHOLD = 'public_stock_display_threshold';

    public const CONTACT_WHATSAPP = 'contact_whatsapp';

    public const CONTACT_EMAIL = 'contact_email';

    public const CONTACT_PHONE = 'contact_phone';

    public const BUSINESS_HOURS_WEEKDAYS = 'business_hours_weekdays';

    public const BUSINESS_HOURS_SATURDAY = 'business_hours_saturday';

    public const FREE_SHIPPING_THRESHOLD = 'free_shipping_threshold';

    public const STOCK_RESERVATION_MINUTES = 'stock_reservation_minutes';

    public const DELIVERY_BUSINESS_DAYS_MIN = 'delivery_business_days_min';

    public const DELIVERY_BUSINESS_DAYS_MAX = 'delivery_business_days_max';

    public const PICKUP_ADDRESS = 'pickup_address';

    protected static array $localCache = [];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function integer(string $key, int $default = 0): int
    {
        return max(0, (int) static::valueFor($key, $default));
    }

    public static function string(string $key, string $default = ''): string
    {
        return trim((string) static::valueFor($key, $default));
    }

    public static function decimal(string $key, string $default = '0.00'): string
    {
        $value = static::valueFor($key, $default);

        if (! is_numeric($value)) {
            $value = $default;
        }

        return number_format(max(0, (float) $value), 2, '.', '');
    }

    public static function publicStockDisplayThreshold(): int
    {
        return static::integer(self::PUBLIC_STOCK_DISPLAY_THRESHOLD, 10);
    }

    public static function setValue(string $key, mixed $value): self
    {
        unset(static::$localCache[$key]);

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setValues(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                static::setValue($key, $value);
            }
        });
    }

    public static function clearLocalCache(): void
    {
        static::$localCache = [];
    }

    private static function valueFor(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$localCache)) {
            return static::$localCache[$key];
        }

        return static::$localCache[$key] = static::query()
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
