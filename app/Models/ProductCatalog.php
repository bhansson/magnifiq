<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class ProductCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function feeds(): HasMany
    {
        return $this->hasMany(ProductFeed::class);
    }

    public function products(): HasManyThrough
    {
        return $this->hasManyThrough(
            Product::class,
            ProductFeed::class,
            'product_catalog_id',
            'product_feed_id',
            'id',
            'id'
        );
    }

    /**
     * Get the languages available in this catalog based on connected feeds.
     */
    public function languages(): Collection
    {
        return $this->feeds()
            ->whereNotNull('language')
            ->pluck('language')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Get the total number of products across all feeds in this catalog.
     */
    public function productsCount(): int
    {
        return $this->products()->count();
    }

    /**
     * Get distinct products (one per SKU).
     * When the same SKU exists in multiple feeds, returns one product per SKU,
     * preferring the specified primary language.
     */
    public function distinctProducts(string $primaryLanguage = 'en'): Collection
    {
        $products = $this->products()
            ->with('feed:id,name,language')
            ->get();

        return $products->groupBy('sku')
            ->map(function ($group) use ($primaryLanguage) {
                // Prefer the primary language version, otherwise take the first
                return $group->first(function ($product) use ($primaryLanguage) {
                    return $product->feed?->language === $primaryLanguage;
                }) ?? $group->first();
            })
            ->values();
    }

    /**
     * Check if catalog is empty (has no feeds).
     */
    public function isEmpty(): bool
    {
        return $this->feeds()->count() === 0;
    }
}
