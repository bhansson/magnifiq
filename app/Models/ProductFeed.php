<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductFeed extends Model
{
    use HasFactory;

    public const SOURCE_TYPE_URL = 'url';

    public const SOURCE_TYPE_UPLOAD = 'upload';

    public const SOURCE_TYPE_STORE_CONNECTION = 'store_connection';

    public const LANGUAGE_OPTIONS = [
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'en' => 'English',
        'en-gb' => 'English (United Kingdom)',
        'en-us' => 'English (United States)',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'hu' => 'Hungarian',
        'it' => 'Italian',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
    ];

    public const LANGUAGE_VALIDATION_RULE = 'required|string|in:bg,cs,da,de,en,en-gb,en-us,es,et,fi,fr,hu,it,lt,lv,nb,nl,no,pl,pt,ro,sk,sl,sv';

    protected $fillable = [
        'team_id',
        'product_catalog_id',
        'store_connection_id',
        'name',
        'feed_url',
        'language',
        'source_type',
        'field_mappings',
    ];

    protected $casts = [
        'field_mappings' => 'array',
    ];

    public static function languageOptions(): array
    {
        return self::LANGUAGE_OPTIONS;
    }

    public static function languageLabel(?string $code): ?string
    {
        return $code !== null ? (self::LANGUAGE_OPTIONS[$code] ?? null) : null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ProductCatalog::class, 'product_catalog_id');
    }

    public function storeConnection(): BelongsTo
    {
        return $this->belongsTo(StoreConnection::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if this feed is part of a catalog.
     */
    public function isInCatalog(): bool
    {
        return $this->product_catalog_id !== null;
    }
}
