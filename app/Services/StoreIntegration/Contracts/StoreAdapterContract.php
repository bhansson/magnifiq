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

    /**
     * Write a metafield to a product in the store.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The external product ID
     * @param  string  $namespace  The metafield namespace
     * @param  string  $key  The metafield key
     * @param  mixed  $value  The metafield value
     * @param  string  $type  The metafield type
     * @return bool True if successful
     *
     * @throws \RuntimeException If the operation fails or is not supported
     */
    public function writeProductMetafield(
        StoreConnection $connection,
        string $productId,
        string $namespace,
        string $key,
        mixed $value,
        string $type = 'json'
    ): bool;

    /**
     * Add an image to a product's media gallery.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The external product ID
     * @param  string  $imageUrl  The publicly accessible URL of the image
     * @param  string|null  $alt  Alt text for the image
     * @return string|null The created media ID, or null on failure
     *
     * @throws \RuntimeException If the operation fails or is not supported
     */
    public function addProductImage(
        StoreConnection $connection,
        string $productId,
        string $imageUrl,
        ?string $alt = null
    ): ?string;

    /**
     * Delete a metafield from a product in the store.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The external product ID
     * @param  string  $namespace  The metafield namespace
     * @param  string  $key  The metafield key
     * @return bool True if successful (or metafield didn't exist)
     *
     * @throws \RuntimeException If the operation fails or is not supported
     */
    public function deleteProductMetafield(
        StoreConnection $connection,
        string $productId,
        string $namespace,
        string $key
    ): bool;
}
