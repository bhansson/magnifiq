<?php

use App\Models\StoreConnection;
use App\Models\Team;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config(['store-integrations.platforms.shopify.client_secret' => 'test-secret']);
});

test('app uninstalled webhook marks connection as disconnected', function () {
    $team = Team::factory()->create();
    $connection = StoreConnection::factory()->create([
        'team_id' => $team->id,
        'platform' => StoreConnection::PLATFORM_SHOPIFY,
        'store_identifier' => 'test-store.myshopify.com',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    $payload = json_encode(['shop_id' => 12345]);
    $hmac = base64_encode(hash_hmac('sha256', $payload, 'test-secret', true));

    $response = $this->postJson('/api/webhooks/shopify/app-uninstalled', json_decode($payload, true), [
        'X-Shopify-Hmac-SHA256' => $hmac,
        'X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
        'X-Shopify-Topic' => 'app/uninstalled',
    ]);

    $response->assertStatus(200);

    $connection->refresh();
    expect($connection->status)->toBe(StoreConnection::STATUS_DISCONNECTED);
    expect($connection->access_token)->toBeNull();
});

test('app uninstalled webhook rejects invalid hmac', function () {
    $payload = json_encode(['shop_id' => 12345]);
    $invalidHmac = base64_encode('invalid-hmac');

    $response = $this->postJson('/api/webhooks/shopify/app-uninstalled', json_decode($payload, true), [
        'X-Shopify-Hmac-SHA256' => $invalidHmac,
        'X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
        'X-Shopify-Topic' => 'app/uninstalled',
    ]);

    $response->assertStatus(401);
});

test('app uninstalled webhook rejects request without hmac header', function () {
    $response = $this->postJson('/api/webhooks/shopify/app-uninstalled', ['shop_id' => 12345], [
        'X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
        'X-Shopify-Topic' => 'app/uninstalled',
    ]);

    $response->assertStatus(401);
});

test('app uninstalled webhook returns 400 without shop domain header', function () {
    $payload = json_encode(['shop_id' => 12345]);
    $hmac = base64_encode(hash_hmac('sha256', $payload, 'test-secret', true));

    $response = $this->postJson('/api/webhooks/shopify/app-uninstalled', json_decode($payload, true), [
        'X-Shopify-Hmac-SHA256' => $hmac,
        'X-Shopify-Topic' => 'app/uninstalled',
    ]);

    $response->assertStatus(400);
});

test('app uninstalled webhook handles unknown store gracefully', function () {
    $payload = json_encode(['shop_id' => 12345]);
    $hmac = base64_encode(hash_hmac('sha256', $payload, 'test-secret', true));

    $response = $this->postJson('/api/webhooks/shopify/app-uninstalled', json_decode($payload, true), [
        'X-Shopify-Hmac-SHA256' => $hmac,
        'X-Shopify-Shop-Domain' => 'unknown-store.myshopify.com',
        'X-Shopify-Topic' => 'app/uninstalled',
    ]);

    $response->assertStatus(200);
});
