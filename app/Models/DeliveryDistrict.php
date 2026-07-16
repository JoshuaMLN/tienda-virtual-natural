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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shipping_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
