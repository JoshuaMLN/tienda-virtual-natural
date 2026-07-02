<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    public const DEFAULT_IMAGE = 'images/placeholders/products/default-prod.webp';

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'benefits',
        'ingredients',
        'usage_instructions',
        'price',
        'compare_at_price',
        'stock',
        'low_stock_threshold',
        'rating_average',
        'reviews_count',
        'is_active',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'rating_average' => 'decimal:2',
            'reviews_count' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true)->oldest('sort_order');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at')->latest('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('category', fn (Builder $query) => $query->active())
            ->where(function (Builder $query) {
                $query->whereNull('brand_id')
                    ->orWhereHas('brand', fn (Builder $query) => $query->active());
            });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%");
        });
    }

    public function getMainImageUrlAttribute(): string
    {
        return $this->primaryImage?->url
            ?? asset(self::DEFAULT_IMAGE);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'S/ '.number_format((float) $this->price, 2);
    }

    public function getFormattedCompareAtPriceAttribute(): ?string
    {
        if ($this->compare_at_price === null) {
            return null;
        }

        return 'S/ '.number_format((float) $this->compare_at_price, 2);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    public static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'producto';
        $slug = $base;
        $suffix = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
