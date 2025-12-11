<?php

namespace App\Services\StoreIntegration\Contracts;

use App\Models\StoreConnection;
use App\Services\StoreIntegration\DTO\OAuthCredentials;
use App\Services\StoreIntegration\DTO\StoreProduct;
use Generator;

interface StoreAdapterContract
{
    /**
     * Get the platform identifier.
     */
    public function getPlatform(): string;

    /**
     * Generate the OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $storeIdentifier, string $state, string $redirectUri): string;

    /**
     * Exchange authorization code for access token.
     */
    public function exchangeCodeForToken(string $storeIdentifier, string $code, string $redirectUri): OAuthCredentials;

    /**
     * Verify HMAC signature from OAuth callback (if applicable).
     */
    public function verifyCallback(array $params): bool;

    /**
     * Fetch products from the store.
     *
     * @return Generator<StoreProduct>
     */
    public function fetchProducts(StoreConnection $connection): Generator;

    /**
     * Fetch a single product by external ID.
     */
    public function fetchProduct(StoreConnection $connection, string $productId): ?StoreProduct;

    /**
     * Test the connection (validate access token).
     */
    public function testConnection(StoreConnection $connection): bool;

    /**
     * Get required OAuth scopes for this platform.
     */
    public function getRequiredScopes(): array;

    /**
     * Get the store name from the connection (for display purposes).
     */
    public function getStoreName(StoreConnection $connection): string;
}
