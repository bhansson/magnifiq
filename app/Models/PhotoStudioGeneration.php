<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoStudioGeneration extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Composition mode constants
     */
    public const MODE_PRODUCTS_TOGETHER = 'products_together';
    public const MODE_BLEND_COLLAGE = 'blend_collage';
    public const MODE_REFERENCE_HERO = 'reference_hero';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'parent_id',
        'user_id',
        'product_id',
        'source_type',
        'source_reference',
        'composition_mode',
        'source_references',
        'prompt',
        'edit_instruction',
        'model',
        'resolution',
        'estimated_cost',
        'storage_disk',
        'storage_path',
        'image_width',
        'image_height',
        'response_id',
        'response_model',
        'response_metadata',
        'product_ai_job_id',
        'pushed_to_store_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'response_metadata' => 'array',
        'source_references' => 'array',
        'estimated_cost' => 'decimal:4',
        'pushed_to_store_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductAiJob::class, 'product_ai_job_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoStudioGeneration::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(PhotoStudioGeneration::class, 'parent_id');
    }

    /**
     * Get all ancestors (parent, grandparent, etc.) in order from oldest to newest
     *
     * @return \Illuminate\Support\Collection<int, PhotoStudioGeneration>
     */
    public function ancestors()
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full generation chain including ancestors and this generation
     *
     * @return \Illuminate\Support\Collection<int, PhotoStudioGeneration>
     */
    public function generationChain()
    {
        return $this->ancestors()->push($this);
    }

    /**
     * Get all descendants (children, grandchildren, etc.) recursively
     *
     * @return \Illuminate\Support\Collection<int, PhotoStudioGeneration>
     */
    public function descendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    /**
     * Get the complete tree: ancestors, current, and all descendants
     *
     * @return array{ancestors: \Illuminate\Support\Collection, current: PhotoStudioGeneration, descendants: \Illuminate\Support\Collection}
     */
    public function fullTree()
    {
        return [
            'ancestors' => $this->ancestors(),
            'current' => $this,
            'descendants' => $this->descendants(),
        ];
    }

    /**
     * Check if this generation was created using composition mode
     */
    public function isComposition(): bool
    {
        return $this->composition_mode !== null;
    }

    /**
     * Get the number of images used in a composition
     */
    public function getCompositionImageCount(): int
    {
        return count($this->source_references ?? []);
    }

    /**
     * Get all products used in a composition
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function compositionProducts()
    {
        $productIds = collect($this->source_references ?? [])
            ->where('type', 'product')
            ->pluck('product_id')
            ->filter();

        return Product::whereIn('id', $productIds)->get();
    }

    /**
     * Get human-readable composition mode label
     */
    public function getCompositionModeLabel(): ?string
    {
        if (! $this->composition_mode) {
            return null;
        }

        return config("photo-studio.composition.modes.{$this->composition_mode}.label", $this->composition_mode);
    }

    /**
     * Get URLs for all source images in a composition.
     *
     * For product images, returns the external URL directly.
     * For uploaded images, returns the route URL to the controller that serves private images.
     *
     * @return array<int, array{url: string|null, type: string, title: string, product_id: int|null}>
     */
    public function getSourceImageUrls(): array
    {
        if (! $this->isComposition() || empty($this->source_references)) {
            return [];
        }

        return collect($this->source_references)
            ->map(function (array $ref, int $index) {
                $url = null;

                if ($ref['type'] === 'product') {
                    $url = $ref['source_reference'] ?? null;
                    if ($url && ! filter_var($url, FILTER_VALIDATE_URL)) {
                        $url = null;
                    }
                } elseif ($ref['type'] === 'upload') {
                    $path = $ref['source_reference'] ?? null;
                    // Only generate URL if image was persisted (has path separator)
                    if ($path && str_contains($path, '/')) {
                        $url = route('photo-studio.generation.source', [
                            'generation' => $this->id,
                            'index' => $index,
                        ]);
                    }
                }

                return [
                    'url' => $url,
                    'type' => $ref['type'],
                    'title' => $ref['title'] ?? 'Untitled',
                    'product_id' => $ref['product_id'] ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Check if this generation has any viewable source images.
     */
    public function hasViewableSourceImages(): bool
    {
        return collect($this->getSourceImageUrls())
            ->contains(fn ($img) => $img['url'] !== null);
    }

    /**
     * Check if this generation has been pushed to a store.
     */
    public function isPushedToStore(): bool
    {
        return $this->pushed_to_store_at !== null;
    }

    /**
     * Mark this generation as pushed to a store.
     */
    public function markAsPushedToStore(): void
    {
        $this->update(['pushed_to_store_at' => now()]);
    }

    /**
     * Get the store connection for this generation's product.
     */
    public function getStoreConnection(): ?StoreConnection
    {
        return $this->product?->feed?->storeConnection;
    }

    /**
     * Check if this generation can be pushed to a store.
     */
    public function canPushToStore(): bool
    {
        $connection = $this->getStoreConnection();

        return $connection !== null && $connection->isConnected() && $this->storage_path !== null;
    }
}
