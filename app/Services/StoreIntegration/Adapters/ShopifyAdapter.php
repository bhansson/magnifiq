<?php

namespace App\Services\StoreIntegration\Adapters;

use App\Models\StoreConnection;
use App\Services\StoreIntegration\DTO\OAuthCredentials;
use App\Services\StoreIntegration\DTO\StoreProduct;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShopifyAdapter extends AbstractStoreAdapter
{
    private const API_VERSION = '2025-10';

    public function getPlatform(): string
    {
        return 'shopify';
    }

    public function getRequiredScopes(): array
    {
        return [
            'read_products',
            'read_inventory',
            'write_products',
            'write_metafields',
            'read_metafield_definitions',
            'write_metafield_definitions',
            // Translation support for multi-language content
            'read_locales',
            'read_translations',
            'write_translations',
        ];
    }

    public function getAuthorizationUrl(string $storeIdentifier, string $state, string $redirectUri): string
    {
        $shop = $this->normalizeShopDomain($storeIdentifier);
        $scopes = implode(',', $this->getRequiredScopes());
        $clientId = $this->getConfig('client_id');

        $params = http_build_query([
            'client_id' => $clientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return "https://{$shop}/admin/oauth/authorize?{$params}";
    }

    public function exchangeCodeForToken(string $storeIdentifier, string $code, string $redirectUri): OAuthCredentials
    {
        $shop = $this->normalizeShopDomain($storeIdentifier);

        $response = Http::timeout(30)->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
            'code' => $code,
        ]);

        if ($response->failed()) {
            $this->logError('Token exchange failed', [
                'shop' => $shop,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Failed to exchange authorization code for access token.');
        }

        $data = $response->json();

        return new OAuthCredentials(
            accessToken: $data['access_token'],
            scopes: explode(',', $data['scope'] ?? ''),
            expiresAt: null, // Shopify offline tokens don't expire
            metadata: [
                'associated_user_scope' => $data['associated_user_scope'] ?? null,
            ],
        );
    }

    public function verifyCallback(array $params): bool
    {
        if (! isset($params['hmac'])) {
            return false;
        }

        $hmac = $params['hmac'];
        unset($params['hmac']);

        ksort($params);
        $message = http_build_query($params);
        $computedHmac = hash_hmac('sha256', $message, $this->getConfig('client_secret'));

        return hash_equals($hmac, $computedHmac);
    }

    public function fetchProducts(StoreConnection $connection): Generator
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);
        $cursor = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $query = $this->buildProductsQuery($cursor);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $connection->access_token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(
                "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
                ['query' => $query]
            );

            if ($response->failed()) {
                throw new RuntimeException('Shopify API request failed: '.$response->body());
            }

            $data = $response->json();

            if (isset($data['errors'])) {
                throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
            }

            $products = $data['data']['products']['edges'] ?? [];
            $pageInfo = $data['data']['products']['pageInfo'] ?? [];

            foreach ($products as $edge) {
                yield $this->mapToStoreProduct($edge['node'], $shop);
            }

            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            $cursor = $pageInfo['endCursor'] ?? null;
        }
    }

    public function fetchProduct(StoreConnection $connection, string $productId): ?StoreProduct
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $query = <<<GRAPHQL
        query {
            product(id: "gid://shopify/Product/{$productId}") {
                id
                title
                descriptionHtml
                handle
                vendor
                productType
                status
                featuredImage { url }
                images(first: 10) { edges { node { url } } }
                variants(first: 100) {
                    edges {
                        node {
                            id
                            title
                            sku
                            barcode
                            price
                            compareAtPrice
                            inventoryQuantity
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => $query]
        );

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $product = $data['data']['product'] ?? null;

        return $product ? $this->mapToStoreProduct($product, $shop) : null;
    }

    public function testConnection(StoreConnection $connection): bool
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => 'query { shop { name } }']
        );

        return $response->successful() && ! isset($response->json()['errors']);
    }

    public function getStoreName(StoreConnection $connection): string
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(15)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => 'query { shop { name } }']
        );

        if ($response->successful()) {
            return $response->json()['data']['shop']['name'] ?? $connection->store_identifier;
        }

        return $connection->store_identifier;
    }

    private function normalizeShopDomain(string $shop): string
    {
        $shop = strtolower(trim($shop));
        $shop = preg_replace('/^https?:\/\//', '', $shop);
        $shop = rtrim($shop, '/');

        if (! str_ends_with($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        return $shop;
    }

    private function buildProductsQuery(?string $cursor): string
    {
        $after = $cursor ? ", after: \"{$cursor}\"" : '';

        return <<<GRAPHQL
        query {
            products(first: 50{$after}) {
                edges {
                    node {
                        id
                        title
                        descriptionHtml
                        handle
                        vendor
                        productType
                        status
                        featuredImage { url }
                        images(first: 10) { edges { node { url } } }
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    barcode
                                    price
                                    compareAtPrice
                                    inventoryQuantity
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GRAPHQL;
    }

    /**
     * Write a metafield to a product in Shopify.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The Shopify product GID (e.g., gid://shopify/Product/123)
     * @param  string  $namespace  The metafield namespace
     * @param  string  $key  The metafield key
     * @param  mixed  $value  The metafield value (will be JSON encoded if array)
     * @param  string  $type  The metafield type (e.g., 'json', 'multi_line_text_field')
     * @return bool True if successful
     *
     * @throws RuntimeException If the API call fails
     */
    public function writeProductMetafield(
        StoreConnection $connection,
        string $productId,
        string $namespace,
        string $key,
        mixed $value,
        string $type = 'json'
    ): bool {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        // Ensure value is a string
        $stringValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        $mutation = <<<'GRAPHQL'
        mutation productUpdate($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $variables = [
            'input' => [
                'id' => $productId,
                'metafields' => [
                    [
                        'namespace' => $namespace,
                        'key' => $key,
                        'value' => $stringValue,
                        'type' => $type,
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => $mutation, 'variables' => $variables]
        );

        if ($response->failed()) {
            throw new RuntimeException('Shopify API request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
        }

        $userErrors = $data['data']['productUpdate']['userErrors'] ?? [];
        if (! empty($userErrors)) {
            throw new RuntimeException('Shopify mutation error: '.json_encode($userErrors));
        }

        return true;
    }

    /**
     * Add an image to a product's media gallery in Shopify.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The Shopify product GID (e.g., gid://shopify/Product/123)
     * @param  string  $imageUrl  The publicly accessible URL of the image
     * @param  string|null  $alt  Alt text for the image
     * @return string|null The created media ID, or null on failure
     *
     * @throws RuntimeException If the API call fails
     */
    public function addProductImage(
        StoreConnection $connection,
        string $productId,
        string $imageUrl,
        ?string $alt = null
    ): ?string {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $mutation = <<<'GRAPHQL'
        mutation productCreateMedia($productId: ID!, $media: [CreateMediaInput!]!) {
            productCreateMedia(productId: $productId, media: $media) {
                media {
                    ... on MediaImage {
                        id
                    }
                }
                mediaUserErrors {
                    code
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $variables = [
            'productId' => $productId,
            'media' => [
                [
                    'originalSource' => $imageUrl,
                    'alt' => $alt ?? '',
                    'mediaContentType' => 'IMAGE',
                ],
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => $mutation, 'variables' => $variables]
        );

        if ($response->failed()) {
            throw new RuntimeException('Shopify API request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
        }

        $mediaErrors = $data['data']['productCreateMedia']['mediaUserErrors'] ?? [];
        if (! empty($mediaErrors)) {
            throw new RuntimeException('Shopify media error: '.json_encode($mediaErrors));
        }

        $media = $data['data']['productCreateMedia']['media'][0] ?? null;

        return $media['id'] ?? null;
    }

    /**
     * Delete a metafield from a product in Shopify.
     *
     * Uses the metafieldsDelete mutation which accepts owner ID + namespace + key.
     *
     * @param  StoreConnection  $connection  The store connection
     * @param  string  $productId  The Shopify product GID (e.g., gid://shopify/Product/123)
     * @param  string  $namespace  The metafield namespace
     * @param  string  $key  The metafield key
     * @return bool True if successful (or metafield didn't exist)
     *
     * @throws RuntimeException If the API call fails
     */
    public function deleteProductMetafield(
        StoreConnection $connection,
        string $productId,
        string $namespace,
        string $key
    ): bool {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $mutation = <<<'GRAPHQL'
        mutation metafieldsDelete($metafields: [MetafieldIdentifierInput!]!) {
            metafieldsDelete(metafields: $metafields) {
                deletedMetafields {
                    key
                    namespace
                    ownerId
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $variables = [
            'metafields' => [
                [
                    'ownerId' => $productId,
                    'namespace' => $namespace,
                    'key' => $key,
                ],
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            ['query' => $mutation, 'variables' => $variables]
        );

        if ($response->failed()) {
            throw new RuntimeException('Shopify API request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
        }

        $userErrors = $data['data']['metafieldsDelete']['userErrors'] ?? [];
        if (! empty($userErrors)) {
            throw new RuntimeException('Shopify mutation error: '.json_encode($userErrors));
        }

        // Note: deletedMetafields will be [null] if the metafield didn't exist,
        // which is fine - we consider that a success (nothing to delete)
        return true;
    }

    private function mapToStoreProduct(array $node, string $shopDomain): StoreProduct
    {
        $variants = collect($node['variants']['edges'] ?? [])
            ->map(fn ($v) => $v['node'])
            ->values()
            ->all();

        $images = collect($node['images']['edges'] ?? [])
            ->map(fn ($i) => $i['node']['url'])
            ->values()
            ->all();

        // Use first variant SKU as primary, or generate from Shopify ID
        $primaryVariant = $variants[0] ?? [];
        $primarySku = $primaryVariant['sku'] ?? null;
        if (! $primarySku) {
            // Extract numeric ID from gid://shopify/Product/123
            $primarySku = 'SHOPIFY-'.preg_replace('/\D/', '', $node['id']);
        }

        $handle = $node['handle'] ?? '';
        $productUrl = $handle ? "https://{$shopDomain}/products/{$handle}" : null;

        return new StoreProduct(
            externalId: $node['id'],
            sku: $primarySku,
            title: $node['title'],
            description: $node['descriptionHtml'] ?? null,
            brand: $node['vendor'] ?? null,
            url: $productUrl,
            imageUrl: $node['featuredImage']['url'] ?? ($images[0] ?? null),
            additionalImages: array_slice($images, 1),
            variants: $variants,
            price: $primaryVariant['price'] ?? null,
            inventory: $primaryVariant['inventoryQuantity'] ?? null,
            gtin: $primaryVariant['barcode'] ?? null,
            metadata: [
                'handle' => $handle,
                'productType' => $node['productType'] ?? null,
                'status' => $node['status'] ?? null,
            ],
        );
    }

    /**
     * Ensure all required metafield definitions exist with PUBLIC_READ access.
     *
     * Theme app extensions require metafield definitions with storefront access
     * to read metafield values. This method creates or updates definitions for
     * all Magnifiq content types.
     */
    public function ensureMetafieldDefinitions(StoreConnection $connection): void
    {
        $definitions = [
            [
                'namespace' => 'magnifiq',
                'key' => 'faq',
                'name' => 'Magnifiq FAQ',
                'description' => 'AI-generated FAQ content from Magnifiq',
                'type' => 'json',
            ],
            [
                'namespace' => 'magnifiq',
                'key' => 'usps',
                'name' => 'Magnifiq USPs',
                'description' => 'AI-generated Unique Selling Points from Magnifiq',
                'type' => 'json',
            ],
            [
                'namespace' => 'magnifiq',
                'key' => 'description',
                'name' => 'Magnifiq Description',
                'description' => 'AI-generated product description from Magnifiq',
                'type' => 'multi_line_text_field',
            ],
            [
                'namespace' => 'magnifiq',
                'key' => 'description_summary',
                'name' => 'Magnifiq Summary',
                'description' => 'AI-generated product summary from Magnifiq',
                'type' => 'multi_line_text_field',
            ],
        ];

        foreach ($definitions as $definition) {
            $this->ensureSingleMetafieldDefinition($connection, $definition);
        }
    }

    /**
     * Ensure a single metafield definition exists with correct access.
     *
     * @param  array{namespace: string, key: string, name: string, description: string, type: string}  $definition
     */
    protected function ensureSingleMetafieldDefinition(StoreConnection $connection, array $definition): void
    {
        $existing = $this->findMetafieldDefinition(
            $connection,
            $definition['namespace'],
            $definition['key']
        );

        if ($existing) {
            // Check if access needs updating
            if (($existing['access']['storefront'] ?? null) !== 'PUBLIC_READ') {
                $this->updateMetafieldDefinitionAccess($connection, $existing['id']);
            }
        } else {
            $this->createMetafieldDefinition($connection, $definition);
        }
    }

    /**
     * Find an existing metafield definition by namespace and key.
     *
     * @return array{id: string, namespace: string, key: string, access: array{storefront: string}}|null
     */
    protected function findMetafieldDefinition(StoreConnection $connection, string $namespace, string $key): ?array
    {
        $query = <<<'GRAPHQL'
        query findMetafieldDefinition($namespace: String!, $key: String!) {
            metafieldDefinitions(
                first: 1,
                ownerType: PRODUCT,
                namespace: $namespace,
                key: $key
            ) {
                nodes {
                    id
                    namespace
                    key
                    access {
                        storefront
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $query, [
            'namespace' => $namespace,
            'key' => $key,
        ]);

        $nodes = $response['data']['metafieldDefinitions']['nodes'] ?? [];

        return $nodes[0] ?? null;
    }

    /**
     * Create a new metafield definition with PUBLIC_READ storefront access.
     *
     * @param  array{namespace: string, key: string, name: string, description: string, type: string}  $definition
     */
    protected function createMetafieldDefinition(StoreConnection $connection, array $definition): void
    {
        $mutation = <<<'GRAPHQL'
        mutation createMetafieldDefinition($definition: MetafieldDefinitionInput!) {
            metafieldDefinitionCreate(definition: $definition) {
                createdDefinition {
                    id
                    namespace
                    key
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $mutation, [
            'definition' => [
                'name' => $definition['name'],
                'namespace' => $definition['namespace'],
                'key' => $definition['key'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'ownerType' => 'PRODUCT',
                'access' => [
                    'storefront' => 'PUBLIC_READ',
                ],
            ],
        ]);

        $errors = $response['data']['metafieldDefinitionCreate']['userErrors'] ?? [];

        if (! empty($errors)) {
            Log::warning('Failed to create metafield definition', [
                'definition' => $definition,
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Update metafield definition to enable PUBLIC_READ storefront access.
     */
    protected function updateMetafieldDefinitionAccess(StoreConnection $connection, string $definitionId): void
    {
        $mutation = <<<'GRAPHQL'
        mutation updateMetafieldDefinitionAccess($definition: MetafieldDefinitionUpdateInput!, $id: ID!) {
            metafieldDefinitionUpdate(definition: $definition, id: $id) {
                updatedDefinition {
                    id
                    access {
                        storefront
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $mutation, [
            'id' => $definitionId,
            'definition' => [
                'access' => [
                    'storefront' => 'PUBLIC_READ',
                ],
            ],
        ]);

        $errors = $response['data']['metafieldDefinitionUpdate']['userErrors'] ?? [];

        if (! empty($errors)) {
            Log::warning('Failed to update metafield definition access', [
                'definitionId' => $definitionId,
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Make a GraphQL request to Shopify.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function graphqlRequest(StoreConnection $connection, string $query, array $variables = []): array
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);

        $payload = ['query' => $query];

        if (! empty($variables)) {
            $payload['variables'] = $variables;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $connection->access_token,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
            $payload
        );

        if ($response->failed()) {
            throw new RuntimeException('Shopify API request failed: '.$response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
        }

        return $data;
    }

    // =========================================================================
    // Product Translation Import Methods
    // =========================================================================

    /**
     * Fetch products with translations merged for a specific locale.
     *
     * This is used when importing products for secondary languages. It fetches
     * the same products as fetchProducts(), but overlays translated title and
     * description for the specified locale.
     *
     * @param  string  $locale  Shopify locale code (e.g., 'de', 'fr', 'es')
     * @return Generator<StoreProduct>
     */
    public function fetchProductsForLocale(StoreConnection $connection, string $locale): Generator
    {
        $shop = $this->normalizeShopDomain($connection->store_identifier);
        $cursor = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $query = $this->buildProductsQueryWithTranslations($cursor, $locale);

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $connection->access_token,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(
                "https://{$shop}/admin/api/".self::API_VERSION.'/graphql.json',
                ['query' => $query]
            );

            if ($response->failed()) {
                throw new RuntimeException('Shopify API request failed: '.$response->body());
            }

            $data = $response->json();

            if (isset($data['errors'])) {
                throw new RuntimeException('Shopify GraphQL error: '.json_encode($data['errors']));
            }

            $products = $data['data']['products']['edges'] ?? [];
            $pageInfo = $data['data']['products']['pageInfo'] ?? [];

            foreach ($products as $edge) {
                yield $this->mapToStoreProductWithTranslations($edge['node'], $shop);
            }

            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            $cursor = $pageInfo['endCursor'] ?? null;
        }
    }

    /**
     * Build GraphQL query that fetches products with their translations.
     */
    private function buildProductsQueryWithTranslations(?string $cursor, string $locale): string
    {
        $after = $cursor ? ", after: \"{$cursor}\"" : '';

        return <<<GRAPHQL
        query {
            products(first: 50{$after}) {
                edges {
                    node {
                        id
                        title
                        descriptionHtml
                        handle
                        vendor
                        productType
                        status
                        featuredImage { url }
                        images(first: 10) { edges { node { url } } }
                        variants(first: 100) {
                            edges {
                                node {
                                    id
                                    title
                                    sku
                                    barcode
                                    price
                                    compareAtPrice
                                    inventoryQuantity
                                }
                            }
                        }
                        translations(locale: "{$locale}") {
                            key
                            value
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GRAPHQL;
    }

    /**
     * Map Shopify product node with translations to StoreProduct DTO.
     */
    private function mapToStoreProductWithTranslations(array $node, string $shopDomain): StoreProduct
    {
        // Extract translations into a keyed array
        $translations = [];
        foreach ($node['translations'] ?? [] as $translation) {
            $translations[$translation['key']] = $translation['value'];
        }

        // Use translated values if available, fall back to primary
        $title = $translations['title'] ?? $node['title'];
        $description = $translations['body_html'] ?? $node['descriptionHtml'] ?? null;

        $variants = collect($node['variants']['edges'] ?? [])
            ->map(fn ($v) => $v['node'])
            ->values()
            ->all();

        $images = collect($node['images']['edges'] ?? [])
            ->map(fn ($i) => $i['node']['url'])
            ->values()
            ->all();

        // Use first variant SKU as primary, or generate from Shopify ID
        $primaryVariant = $variants[0] ?? [];
        $primarySku = $primaryVariant['sku'] ?? null;
        if (! $primarySku) {
            $primarySku = 'SHOPIFY-'.preg_replace('/\D/', '', $node['id']);
        }

        $handle = $node['handle'] ?? '';
        $productUrl = $handle ? "https://{$shopDomain}/products/{$handle}" : null;

        return new StoreProduct(
            externalId: $node['id'],
            sku: $primarySku,
            title: $title,
            description: $description,
            brand: $node['vendor'] ?? null,
            url: $productUrl,
            imageUrl: $node['featuredImage']['url'] ?? ($images[0] ?? null),
            additionalImages: array_slice($images, 1),
            variants: $variants,
            price: $primaryVariant['price'] ?? null,
            inventory: $primaryVariant['inventoryQuantity'] ?? null,
            gtin: $primaryVariant['barcode'] ?? null,
            metadata: [
                'handle' => $handle,
                'productType' => $node['productType'] ?? null,
                'status' => $node['status'] ?? null,
            ],
        );
    }

    // =========================================================================
    // Translation API Methods (for writing translations TO Shopify)
    // =========================================================================

    /**
     * Get the shop's enabled locales.
     *
     * @return array<int, array{locale: string, name: string, primary: bool, published: bool}>
     */
    public function getShopLocales(StoreConnection $connection): array
    {
        $query = <<<'GRAPHQL'
        query {
            shopLocales(published: false) {
                locale
                name
                primary
                published
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $query);

        return $response['data']['shopLocales'] ?? [];
    }

    /**
     * Get the shop's primary locale code.
     */
    public function getPrimaryLocale(StoreConnection $connection): ?string
    {
        $locales = $this->getShopLocales($connection);

        foreach ($locales as $locale) {
            if ($locale['primary'] ?? false) {
                return $locale['locale'];
            }
        }

        return null;
    }

    /**
     * Get the translatable content for a product's metafield.
     * Returns the digest needed for registering translations.
     *
     * @return array{metafieldId: string, digest: string, locale: string, value: string}|null
     */
    public function getMetafieldTranslatableContent(
        StoreConnection $connection,
        string $productId,
        string $namespace,
        string $key
    ): ?array {
        $query = <<<'GRAPHQL'
        query getTranslatableMetafield($resourceId: ID!) {
            translatableResource(resourceId: $resourceId) {
                resourceId
                nestedTranslatableResources(resourceType: METAFIELD, first: 50) {
                    nodes {
                        resourceId
                        translatableContent {
                            key
                            value
                            digest
                            locale
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $query, [
            'resourceId' => $productId,
        ]);

        $nestedResources = $response['data']['translatableResource']['nestedTranslatableResources']['nodes'] ?? [];

        // Check each nested metafield resource
        foreach ($nestedResources as $resource) {
            $metafieldId = $resource['resourceId'];

            // Verify this is the metafield we're looking for
            if ($this->isMetafieldMatch($connection, $metafieldId, $namespace, $key)) {
                // Find the "value" translatable content (metafields have key="value")
                foreach ($resource['translatableContent'] as $content) {
                    if ($content['key'] === 'value') {
                        return [
                            'metafieldId' => $metafieldId,
                            'digest' => $content['digest'],
                            'locale' => $content['locale'],
                            'value' => $content['value'],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if a metafield GID matches the expected namespace and key.
     */
    protected function isMetafieldMatch(
        StoreConnection $connection,
        string $metafieldId,
        string $namespace,
        string $key
    ): bool {
        $query = <<<'GRAPHQL'
        query getMetafield($id: ID!) {
            node(id: $id) {
                ... on Metafield {
                    namespace
                    key
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $query, ['id' => $metafieldId]);

        $metafield = $response['data']['node'] ?? null;

        return $metafield
            && ($metafield['namespace'] ?? '') === $namespace
            && ($metafield['key'] ?? '') === $key;
    }

    /**
     * Register a translation for a metafield.
     *
     * @param  string  $metafieldId  The metafield GID (gid://shopify/Metafield/xxx)
     * @param  string  $locale  Target locale (e.g., 'de', 'fr')
     * @param  mixed  $value  Translated content
     * @param  string  $digest  Digest from translatableContent of the primary value
     */
    public function registerTranslation(
        StoreConnection $connection,
        string $metafieldId,
        string $locale,
        mixed $value,
        string $digest
    ): bool {
        $mutation = <<<'GRAPHQL'
        mutation translationsRegister($resourceId: ID!, $translations: [TranslationInput!]!) {
            translationsRegister(resourceId: $resourceId, translations: $translations) {
                translations {
                    locale
                    key
                    value
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        // Ensure value is a string
        $stringValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        $response = $this->graphqlRequest($connection, $mutation, [
            'resourceId' => $metafieldId,
            'translations' => [
                [
                    'locale' => $locale,
                    'key' => 'value',  // Metafield's translatable field is always "value"
                    'value' => $stringValue,
                    'translatableContentDigest' => $digest,
                ],
            ],
        ]);

        $userErrors = $response['data']['translationsRegister']['userErrors'] ?? [];

        if (! empty($userErrors)) {
            Log::warning('Failed to register translation', [
                'metafieldId' => $metafieldId,
                'locale' => $locale,
                'errors' => $userErrors,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Remove a translation for a metafield.
     */
    public function removeTranslation(
        StoreConnection $connection,
        string $metafieldId,
        string $locale
    ): bool {
        $mutation = <<<'GRAPHQL'
        mutation translationsRemove($resourceId: ID!, $translationKeys: [String!]!, $locales: [String!]!) {
            translationsRemove(
                resourceId: $resourceId,
                translationKeys: $translationKeys,
                locales: $locales
            ) {
                translations {
                    locale
                    key
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $response = $this->graphqlRequest($connection, $mutation, [
            'resourceId' => $metafieldId,
            'translationKeys' => ['value'],
            'locales' => [$locale],
        ]);

        $userErrors = $response['data']['translationsRemove']['userErrors'] ?? [];

        if (! empty($userErrors)) {
            Log::warning('Failed to remove translation', [
                'metafieldId' => $metafieldId,
                'locale' => $locale,
                'errors' => $userErrors,
            ]);

            return false;
        }

        return true;
    }
}
