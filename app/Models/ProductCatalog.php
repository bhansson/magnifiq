<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductCatalog $catalog) {
            if (empty($catalog->slug)) {
                $catalog->slug = $catalog->generateUniqueSlug($catalog->name);
            }
        });

        static::updating(function (ProductCatalog $catalog) {
            // Regenerate slug if name changed and slug wasn't explicitly set
            if ($catalog->isDirty('name') && ! $catalog->isDirty('slug')) {
                $catalog->slug = $catalog->generateUniqueSlug($catalog->name);
            }
        });
    }

    /**
     * Get the route key name for Laravel route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a unique slug within the team.
     */
    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'catalog';
        $slug = $baseSlug;
        $counter = 1;

        $query = static::where('team_id', $this->team_id)->where('slug', $slug);

        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter++;
            $query = static::where('team_id', $this->team_id)->where('slug', $slug);

            if ($this->exists) {
                $query->where('id', '!=', $this->id);
            }
        }

        return $slug;
    }

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

    /**
     * Find a product by SKU within this catalog.
     * Optionally prefer a specific language, otherwise returns the first match.
     */
    public function findProductBySku(string $sku, ?string $preferredLanguage = null): ?Product
    {
        $query = $this->products()->where('products.sku', $sku);

        if ($preferredLanguage) {
            // Try to find product in preferred language first
            $product = (clone $query)
                ->whereHas('feed', fn ($q) => $q->where('language', $preferredLanguage))
                ->first();

            if ($product) {
                return $product;
            }
        }

        // Fall back to first available product with this SKU
        return $query->first();
    }

    /**
     * Get all products matching a SKU (all language versions).
     */
    public function getProductsBySku(string $sku): Collection
    {
        return $this->products()
            ->where('products.sku', $sku)
            ->with('feed:id,name,language')
            ->get();
    }
}
