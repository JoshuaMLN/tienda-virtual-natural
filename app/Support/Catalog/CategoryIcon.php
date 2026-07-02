<?php

namespace App\Support\Catalog;

class CategoryIcon
{
    public static function options(): array
    {
        return [
            'bi-capsule-pill' => 'Vitaminas',
            'bi-prescription2' => 'Suplementos',
            'bi-basket2' => 'Snacks',
            'bi-flower1' => 'Superfoods',
            'bi-egg-fried' => 'Proteinas',
            'bi-droplet' => 'Belleza',
            'bi-cup-hot' => 'Infusiones',
            'bi-emoji-smile' => 'Ninos',
            'bi-heart-pulse' => 'Salud',
            'bi-tree' => 'Natural',
            'bi-stars' => 'Destacado',
            'bi-grid' => 'General',
        ];
    }

    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function default(): string
    {
        return 'bi-grid';
    }
}
