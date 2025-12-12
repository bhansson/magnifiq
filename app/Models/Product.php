<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_feed_id',
        'team_id',
        'sku',
        'external_id',
        'gtin',
        'title',
        'brand',
        'description',
        'url',
        'image_link',
        'additional_image_link',
    ];

    public function feed()
    {
        return $this->belongsTo(ProductFeed::class, 'product_feed_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(ProductAiGeneration::class);
    }

    public function latestAiGeneration(): HasOne
    {
        return $this->hasOne(ProductAiGeneration::class)->latestOfMany('updated_at');
    }

    public function aiDescriptionSummaries(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY);
    }

    public function aiDescriptions(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_DESCRIPTION);
    }

    public function aiUsps(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_USPS);
    }

    public function aiFaqs(): HasMany
    {
        return $this->aiGenerationsForTemplate(ProductAiTemplate::SLUG_FAQ);
    }

    public function latestAiDescriptionSummary(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_DESCRIPTION_SUMMARY);
    }

    public function latestAiDescription(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_DESCRIPTION);
    }

    public function latestAiUsp(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_USPS);
    }

    public function latestAiFaq(): HasOne
    {
        return $this->latestAiGenerationForTemplate(ProductAiTemplate::SLUG_FAQ);
    }

    public function latestAiGenerationForTemplate(string $slug): HasOne
    {
        return $this->hasOne(ProductAiGeneration::class)
            ->whereHas('template', static function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->latestOfMany('updated_at');
    }

    public function aiGenerationsForTemplate(string $slug): HasMany
    {
        return $this->hasMany(ProductAiGeneration::class)
            ->whereHas('template', static function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->latest();
    }

    /**
     * Get sibling products with the same SKU in other feeds of the same catalog.
     * Returns empty collection if product is not in a catalog.
     */
    public function siblingProducts(): Collection
    {
        if (! $this->feed?->product_catalog_id) {
            return collect();
        }

        return Product::query()
            ->where('sku', $this->sku)
            ->where('team_id', $this->team_id)
            ->where('id', '!=', $this->id)
            ->whereHas('feed', fn ($q) => $q->where('product_catalog_id', $this->feed->product_catalog_id))
            ->with('feed:id,name,language')
            ->get();
    }

    /**
     * Get all language versions of this product (including self).
     * Returns collection with only self if product is not in a catalog.
     */
    public function allLanguageVersions(): Collection
    {
        if (! $this->feed?->product_catalog_id) {
            return collect([$this]);
        }

        return Product::query()
            ->where('sku', $this->sku)
            ->where('team_id', $this->team_id)
            ->whereHas('feed', fn ($q) => $q->where('product_catalog_id', $this->feed->product_catalog_id))
            ->with(['feed:id,name,language,product_catalog_id', 'feed.catalog:id,slug'])
            ->get()
            ->sortBy(fn ($product) => $product->feed?->language ?? 'zzz');
    }

    /**
     * Check if this product has sibling products in other languages.
     */
    public function hasLanguageSiblings(): bool
    {
        return $this->siblingProducts()->isNotEmpty();
    }

    /**
     * Check if this product is part of a catalog.
     */
    public function isInCatalog(): bool
    {
        return $this->feed?->product_catalog_id !== null;
    }

    /**
     * Check if this product has a semantic URL (not legacy).
     */
    public function hasSemanticUrl(): bool
    {
        return $this->isInCatalog() && $this->sku && $this->feed?->catalog?->slug;
    }

    /**
     * Get the URL for this product.
     * Returns a semantic URL if product is in a catalog with a SKU, otherwise null.
     */
    public function getUrl(): ?string
    {
        if (! $this->hasSemanticUrl()) {
            return null;
        }

        $params = [
            'catalog' => $this->feed->catalog->slug,
            'sku' => $this->sku,
        ];

        // Only include language in URL if product has one
        if ($this->feed->language) {
            $params['lang'] = $this->feed->language;
        }

        return route('products.show', $params);
    }
}
