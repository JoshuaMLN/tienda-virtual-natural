<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

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

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
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

    /**
     * Returns true when the product is published (published_at in the past)
     * but hidden because its category or brand is inactive.
     */
    public function getIsHiddenByRelationsAttribute(): bool
    {
        if (! $this->published_at || ! $this->published_at->isPast()) {
            return false;
        }

        $categoryInactive = $this->relationLoaded('category')
            ? ($this->category && ! $this->category->is_active)
            : $this->category()->where('is_active', false)->exists();

        $brandInactive = $this->brand_id !== null && (
            $this->relationLoaded('brand')
                ? ($this->brand && ! $this->brand->is_active)
                : $this->brand()->where('is_active', false)->exists()
        );

        return $categoryInactive || $brandInactive;
    }

    /**
     * Returns true when a product with a past publication date is not visible
     * in the public store because the product, category, or brand is inactive.
     */
    public function getIsHiddenFromStoreAttribute(): bool
    {
        if (! $this->published_at || ! $this->published_at->isPast()) {
            return false;
        }

        return ! $this->is_active || $this->is_hidden_by_relations;
    }

    /**
     * Returns 'publicado', 'oculto', 'programado', or 'sin-publicar'
     * based on the product's full visibility state.
     */
    public function getVisibilityStatusAttribute(): string
    {
        if (! $this->published_at) {
            return 'sin-publicar';
        }

        if ($this->published_at->isFuture()) {
            return 'programado';
        }

        if ($this->is_hidden_from_store) {
            return 'oculto';
        }

        return 'publicado';
    }

    /**
     * Returns an explanatory tooltip message when visibility_status === 'oculto'.
     * Returns an empty string otherwise.
     */
    public function getVisibilityTooltipAttribute(): string
    {
        if ($this->visibility_status !== 'oculto') {
            return '';
        }

        $reasons = [];

        if (! $this->is_active) {
            $reasons[] = 'el producto esta inactivo';
        }

        $categoryInactiveForTooltip = $this->relationLoaded('category')
            ? ($this->category && ! $this->category->is_active)
            : $this->category()->where('is_active', false)->exists();

        if ($categoryInactiveForTooltip) {
            $reasons[] = 'su categoria esta inactiva';
        }

        $brandInactiveForTooltip = $this->brand_id !== null && (
            $this->relationLoaded('brand')
                ? ($this->brand && ! $this->brand->is_active)
                : $this->brand()->where('is_active', false)->exists()
        );

        if ($brandInactiveForTooltip) {
            $reasons[] = 'su marca esta inactiva';
        }

        return 'Oculto porque '.implode(' y ', $reasons).'.';
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return 'sin-stock';
        }

        if ($this->low_stock_threshold > 0 && $this->stock <= $this->low_stock_threshold) {
            return 'bajo-stock';
        }

        return 'optimo';
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'sin-stock' => 'Sin stock',
            'bajo-stock' => 'Bajo stock',
            default => 'Optimo',
        };
    }

    public function getPublicStockLabelAttribute(): string
    {
        if (! $this->is_in_stock) {
            return 'Sin stock';
        }

        $threshold = Setting::publicStockDisplayThreshold();

        if ($threshold <= 0) {
            return 'En stock';
        }

        if ($this->stock > $threshold) {
            return "Mas de {$threshold} disponibles";
        }

        return 'Queda'.($this->stock === 1 ? '' : 'n').' '.$this->stock.' unidad'.($this->stock === 1 ? '' : 'es');
    }

    public function getPublicStockSummaryLabelAttribute(): string
    {
        if (! $this->is_in_stock) {
            return 'Sin stock';
        }

        $threshold = Setting::publicStockDisplayThreshold();

        if ($threshold > 0 && $this->stock <= $threshold) {
            return 'Quedan pocas unidades';
        }

        return 'En stock';
    }

    public function getPublicStockTextClassAttribute(): string
    {
        if (! $this->is_in_stock) {
            return 'text-danger';
        }

        $threshold = Setting::publicStockDisplayThreshold();

        if ($threshold > 0 && $this->stock <= $threshold) {
            return 'text-warning';
        }

        return 'text-success';
    }

    public function getPublicStockIconAttribute(): string
    {
        if (! $this->is_in_stock) {
            return 'bi-x-circle';
        }

        $threshold = Setting::publicStockDisplayThreshold();

        if ($threshold > 0 && $this->stock <= $threshold) {
            return 'bi-exclamation-triangle';
        }

        return 'bi-check-circle';
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
