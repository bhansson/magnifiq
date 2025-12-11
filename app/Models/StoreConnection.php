<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StoreConnection extends Model
{
    use HasFactory;

    public const PLATFORM_SHOPIFY = 'shopify';

    public const PLATFORM_WOOCOMMERCE = 'woocommerce';

    public const PLATFORM_BIGCOMMERCE = 'bigcommerce';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_SYNCING = 'syncing';

    public const STATUS_ERROR = 'error';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'team_id',
        'product_feed_id',
        'platform',
        'name',
        'store_identifier',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'last_error',
        'last_synced_at',
        'sync_settings',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'sync_settings' => 'array',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function productFeed(): HasOne
    {
        return $this->hasOne(ProductFeed::class);
    }

    public function syncJobs(): HasMany
    {
        return $this->hasMany(StoreSyncJob::class);
    }

    public function latestSyncJob(): HasOne
    {
        return $this->hasOne(StoreSyncJob::class)->latestOfMany();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isShopify(): bool
    {
        return $this->platform === self::PLATFORM_SHOPIFY;
    }

    public function getSyncIntervalMinutes(): int
    {
        return $this->sync_settings['interval_minutes'] ?? 60;
    }

    public function needsSync(): bool
    {
        if (! $this->isConnected()) {
            return false;
        }

        if (! $this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->getSyncIntervalMinutes())->isPast();
    }

    public function markConnected(string $accessToken, array $scopes, ?array $metadata = null): void
    {
        $this->update([
            'access_token' => $accessToken,
            'scopes' => $scopes,
            'status' => self::STATUS_CONNECTED,
            'last_error' => null,
            'metadata' => array_merge($this->metadata ?? [], $metadata ?? []),
        ]);
    }

    public function markError(string $error): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'last_error' => $error,
        ]);
    }

    public function markDisconnected(): void
    {
        $this->update([
            'status' => self::STATUS_DISCONNECTED,
            'access_token' => null,
            'refresh_token' => null,
        ]);
    }

    public function markSyncing(): void
    {
        $this->update([
            'status' => self::STATUS_SYNCING,
        ]);
    }

    public function markSynced(): void
    {
        $this->update([
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Get the display name for the platform.
     */
    public function getPlatformDisplayName(): string
    {
        return match ($this->platform) {
            self::PLATFORM_SHOPIFY => 'Shopify',
            self::PLATFORM_WOOCOMMERCE => 'WooCommerce',
            self::PLATFORM_BIGCOMMERCE => 'BigCommerce',
            default => ucfirst($this->platform),
        };
    }

    /**
     * Get a user-friendly error message from the technical error.
     */
    public function getFriendlyError(): ?string
    {
        if (! $this->last_error) {
            return null;
        }

        $error = $this->last_error;

        // DNS/Network errors
        if (str_contains($error, 'Could not resolve host')) {
            return 'Unable to connect to the store. Please check your internet connection and try again.';
        }

        if (str_contains($error, 'Connection timed out') || str_contains($error, 'Operation timed out')) {
            return 'Connection timed out. The store may be temporarily unavailable.';
        }

        if (str_contains($error, 'Connection refused')) {
            return 'Connection refused. The store may be temporarily unavailable.';
        }

        // SSL/Certificate errors
        if (str_contains($error, 'SSL') || str_contains($error, 'certificate')) {
            return 'Secure connection failed. Please try again later.';
        }

        // Authentication errors
        if (str_contains($error, 'Invalid API key') || str_contains($error, 'access token') || str_contains($error, '401')) {
            return 'Authentication failed. Please reconnect your store.';
        }

        if (str_contains($error, 'Access denied') || str_contains($error, '403')) {
            return 'Access denied. The app may need additional permissions.';
        }

        // Rate limiting
        if (str_contains($error, '429') || str_contains($error, 'rate limit') || str_contains($error, 'throttled')) {
            return 'Too many requests. Please wait a moment and try again.';
        }

        // Store not found
        if (str_contains($error, '404') || str_contains($error, 'not found')) {
            return 'Store not found. Please verify the store URL.';
        }

        // Server errors
        if (str_contains($error, '500') || str_contains($error, '502') || str_contains($error, '503')) {
            return 'The store is temporarily unavailable. Please try again later.';
        }

        // Generic cURL errors
        if (str_contains($error, 'cURL error')) {
            return 'Unable to connect to the store. Please try again later.';
        }

        // Default: truncate and show a generic message with hint
        return 'Sync failed. Please try again or reconnect your store.';
    }
}
