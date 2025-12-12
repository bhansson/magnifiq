<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAiGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'product_id',
        'product_ai_template_id',
        'product_ai_job_id',
        'sku',
        'content',
        'meta',
        'unpublished_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'unpublished_at' => 'datetime',
    ];

    protected $touches = ['product'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProductAiTemplate::class, 'product_ai_template_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProductAiJob::class, 'product_ai_job_id');
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: static function ($value) {
                if ($value === null) {
                    return null;
                }

                $decoded = json_decode($value, true);

                return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            },
            set: static function ($value) {
                if ($value === null) {
                    return null;
                }

                if (is_string($value)) {
                    return json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        );
    }

    /**
     * Check if this generation is published (synced to store).
     */
    public function isPublished(): bool
    {
        return $this->unpublished_at === null;
    }

    /**
     * Check if this generation is unpublished (hidden in store).
     */
    public function isUnpublished(): bool
    {
        return $this->unpublished_at !== null;
    }

    /**
     * Mark this generation as unpublished.
     */
    public function markAsUnpublished(): void
    {
        $this->update(['unpublished_at' => now()]);
    }

    /**
     * Mark this generation as published (re-publish after unpublish).
     */
    public function markAsPublished(): void
    {
        $this->update(['unpublished_at' => null]);
    }

    /**
     * Get the store connection for this generation's product.
     */
    public function getStoreConnection(): ?StoreConnection
    {
        return $this->product?->feed?->storeConnection;
    }

    /**
     * Check if this generation can be synced to a store.
     */
    public function canSyncToStore(): bool
    {
        $connection = $this->getStoreConnection();

        return $connection !== null && $connection->isConnected();
    }
}
