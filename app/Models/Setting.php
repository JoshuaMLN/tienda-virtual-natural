<?php

namespace App\Models;

use App\Support\Money\Money;
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

    public const LEGAL_TRADE_NAME = 'legal_trade_name';

    public const LEGAL_PROVIDER_NAME = 'legal_provider_name';

    public const LEGAL_TAX_ID = 'legal_tax_id';

    public const LEGAL_FISCAL_ADDRESS = 'legal_fiscal_address';

    public const LEGAL_COMPLAINTS_BOOK_URL = 'legal_complaints_book_url';

    public const LIVE_SALES_ENABLED = 'live_sales_enabled';

    public const INCIDENT_REPORT_HOURS = 'incident_report_hours';

    public const REFUND_PROCESSING_BUSINESS_DAYS = 'refund_processing_business_days';

    public const DELIVERY_ATTEMPTS_PER_CYCLE = 'delivery_attempts_per_cycle';

    public const DELIVERY_MAX_AUTOMATIC_CYCLES = 'delivery_max_automatic_cycles';

    public const RESHIPMENT_PAYMENT_DAYS = 'reshipment_payment_days';

    public const PICKUP_HOLD_DAYS = 'pickup_hold_days';

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

        return Money::fromDecimal($value)->decimal();
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
