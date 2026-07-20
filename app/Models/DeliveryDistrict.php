<?php

namespace App\Models;

use Database\Factories\DeliveryDistrictFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryDistrict extends Model
{
    /** @use HasFactory<DeliveryDistrictFactory> */
    use HasFactory;

    protected $fillable = [
        'ubigeo',
        'province_code',
        'department',
        'province',
        'district',
        'shipping_fee',
        'delivery_business_days_min',
        'delivery_business_days_max',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'decimal:2',
            'delivery_business_days_min' => 'integer',
            'delivery_business_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return array{int, int} */
    public function deliveryWindow(int $fallbackMinimum, int $fallbackMaximum): array
    {
        if ($this->delivery_business_days_min === null || $this->delivery_business_days_max === null) {
            return [$fallbackMinimum, $fallbackMaximum];
        }

        return [$this->delivery_business_days_min, $this->delivery_business_days_max];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
