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
        'storage_disk',
        'storage_path',
        'image_width',
        'image_height',
        'response_id',
        'response_model',
        'response_metadata',
        'product_ai_job_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'response_metadata' => 'array',
        'source_references' => 'array',
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
}
