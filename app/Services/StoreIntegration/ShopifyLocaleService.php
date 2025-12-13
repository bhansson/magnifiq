<?php

namespace App\Services\StoreIntegration;

use App\Models\StoreConnection;
use App\Services\StoreIntegration\Adapters\ShopifyAdapter;
use Illuminate\Support\Facades\Cache;

class ShopifyLocaleService
{
    /**
     * Map Magnifiq language codes to Shopify locale codes.
     * Magnifiq uses ISO 639-1 codes, Shopify uses BCP 47.
     */
    protected const MAGNIFIQ_TO_SHOPIFY = [
        // Direct mappings (same code)
        'bg' => 'bg',
        'cs' => 'cs',
        'da' => 'da',
        'de' => 'de',
        'en' => 'en',
        'es' => 'es',
        'et' => 'et',
        'fi' => 'fi',
        'fr' => 'fr',
        'hu' => 'hu',
        'it' => 'it',
        'lt' => 'lt',
        'lv' => 'lv',
        'nl' => 'nl',
        'pl' => 'pl',
        'pt' => 'pt',
        'ro' => 'ro',
        'sk' => 'sk',
        'sl' => 'sl',
        'sv' => 'sv',
        'ja' => 'ja',
        // Regional variants
        'en-gb' => 'en-GB',
        'en-us' => 'en-US',
        'pt-br' => 'pt-BR',
        'nb' => 'nb',  // Norwegian Bokmal
        'no' => 'nb',  // Norwegian -> Norwegian Bokmal
    ];

    /**
     * Map Shopify locale codes back to Magnifiq language codes.
     * Used when importing translations from Shopify stores.
     */
    protected const SHOPIFY_TO_MAGNIFIQ = [
        // Direct mappings (same code, lowercase)
        'bg' => 'bg',
        'cs' => 'cs',
        'da' => 'da',
        'de' => 'de',
        'en' => 'en',
        'es' => 'es',
        'et' => 'et',
        'fi' => 'fi',
        'fr' => 'fr',
        'hu' => 'hu',
        'it' => 'it',
        'lt' => 'lt',
        'lv' => 'lv',
        'nl' => 'nl',
        'pl' => 'pl',
        'pt' => 'pt',
        'ro' => 'ro',
        'sk' => 'sk',
        'sl' => 'sl',
        'sv' => 'sv',
        'ja' => 'ja',
        'nb' => 'nb',
        // Regional variants (Shopify returns uppercase region)
        'en-gb' => 'en-gb',
        'en-us' => 'en-us',
        'pt-br' => 'pt-br',
        'de-de' => 'de',
        'de-at' => 'de',
        'de-ch' => 'de',
        'fr-fr' => 'fr',
        'fr-ca' => 'fr',
        'es-es' => 'es',
        'es-mx' => 'es',
        'it-it' => 'it',
        'nl-nl' => 'nl',
        'nl-be' => 'nl',
    ];

    public function __construct(
        protected ShopifyAdapter $adapter
    ) {}

    /**
     * Resolve Magnifiq language code to Shopify locale.
     */
    public function resolveShopifyLocale(string $magnifiqLanguage): string
    {
        $normalized = strtolower(trim($magnifiqLanguage));

        return self::MAGNIFIQ_TO_SHOPIFY[$normalized] ?? $magnifiqLanguage;
    }

    /**
     * Map a Shopify BCP 47 locale code back to Magnifiq language code.
     *
     * Handles both simple locales (en, de) and regional variants (en-GB, pt-BR).
     * Regional variants without specific mappings fall back to base language.
     */
    public function mapShopifyLocaleToMagnifiq(string $shopifyLocale): string
    {
        $normalized = strtolower(trim($shopifyLocale));

        // Check for exact match first (handles regional variants)
        if (isset(self::SHOPIFY_TO_MAGNIFIQ[$normalized])) {
            return self::SHOPIFY_TO_MAGNIFIQ[$normalized];
        }

        // Fall back to base language for unmapped regional variants
        $baseLanguage = explode('-', $normalized)[0];

        return self::SHOPIFY_TO_MAGNIFIQ[$baseLanguage] ?? $baseLanguage;
    }

    /**
     * Check if the given language matches the store's primary locale.
     */
    public function isPrimaryLanguage(StoreConnection $connection, string $language): bool
    {
        $primaryLocale = $this->getPrimaryLocale($connection);

        if (! $primaryLocale) {
            // No primary locale found, treat as primary to avoid breaking sync
            return true;
        }

        $productLocale = $this->resolveShopifyLocale($language);

        // Compare base language (e.g., "en" matches "en-US", "en-GB")
        return $this->localesMatch($primaryLocale, $productLocale);
    }

    /**
     * Get the store's primary locale (cached).
     */
    public function getPrimaryLocale(StoreConnection $connection): ?string
    {
        $cacheKey = "shopify_primary_locale_{$connection->id}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($connection) {
            return $this->adapter->getPrimaryLocale($connection);
        });
    }

    /**
     * Get the store's published locales (cached).
     *
     * @return array<int, array{locale: string, name: string, primary: bool, published: bool}>
     */
    public function getPublishedLocales(StoreConnection $connection): array
    {
        $cacheKey = "shopify_published_locales_{$connection->id}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($connection) {
            $locales = $this->adapter->getShopLocales($connection);

            return array_values(array_filter($locales, fn ($l) => $l['published'] ?? false));
        });
    }

    /**
     * Check if a locale is published in the store.
     */
    public function isLocalePublished(StoreConnection $connection, string $language): bool
    {
        $targetLocale = $this->resolveShopifyLocale($language);
        $publishedLocales = $this->getPublishedLocales($connection);

        foreach ($publishedLocales as $locale) {
            if ($this->localesMatch($locale['locale'], $targetLocale)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear cached locales for a connection.
     */
    public function clearCache(StoreConnection $connection): void
    {
        Cache::forget("shopify_primary_locale_{$connection->id}");
        Cache::forget("shopify_published_locales_{$connection->id}");
    }

    /**
     * Check if two locale codes match (considering regional variants).
     * "en" matches "en-US" and "en-GB", "de" matches "de-DE", etc.
     */
    protected function localesMatch(string $locale1, string $locale2): bool
    {
        $base1 = explode('-', strtolower($locale1))[0];
        $base2 = explode('-', strtolower($locale2))[0];

        return $base1 === $base2;
    }
}
