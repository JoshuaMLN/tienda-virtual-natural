<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const PUBLIC_STOCK_DISPLAY_THRESHOLD = 'public_stock_display_threshold';

    protected static array $localCache = [];

    protected $fillable = [
        'key',
        'value',
    ];

    public static function integer(string $key, int $default = 0): int
    {
        return max(0, (int) static::valueFor($key, $default));
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
