<?php

namespace App\Services\StoreIntegration\Adapters;

use App\Models\StoreConnection;
use App\Services\StoreIntegration\DTO\OAuthCredentials;
use App\Services\StoreIntegration\DTO\StoreProduct;
use Generator;
use Illuminate\Support\Facades\Http;
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
}
