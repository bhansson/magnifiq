<?php

use App\Jobs\SyncStoreProducts;
use App\Models\StoreConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;

    config([
        'store-integrations.platforms.shopify.client_id' => 'test-client-id',
        'store-integrations.platforms.shopify.client_secret' => 'test-client-secret',
    ]);
});

test('oauth redirect generates correct authorization url', function () {
    $response = $this->actingAs($this->user)
        ->get(route('store.oauth.redirect', [
            'platform' => 'shopify',
            'store' => 'test-store.myshopify.com',
        ]));

    $response->assertRedirect();

    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toContain('test-store.myshopify.com/admin/oauth/authorize');
    expect($redirectUrl)->toContain('client_id=test-client-id');
    expect($redirectUrl)->toContain('scope=read_products%2Cread_inventory');
});

test('oauth redirect requires store parameter', function () {
    $response = $this->actingAs($this->user)
        ->get(route('store.oauth.redirect', ['platform' => 'shopify']));

    $response->assertStatus(302);
    $response->assertSessionHasErrors('store');
});

test('oauth redirect stores state in session', function () {
    $this->actingAs($this->user)
        ->get(route('store.oauth.redirect', [
            'platform' => 'shopify',
            'store' => 'test-store',
        ]));

    expect(session('store_oauth_state'))->not()->toBeNull();
    expect(session('store_oauth_platform'))->toBe('shopify');
    expect(session('store_oauth_team_id'))->toBe($this->team->id);
});

test('oauth callback validates state', function () {
    $response = $this->actingAs($this->user)
        ->withSession([
            'store_oauth_state' => 'valid-state',
            'store_oauth_platform' => 'shopify',
            'store_oauth_store' => 'test-store.myshopify.com',
            'store_oauth_team_id' => $this->team->id,
        ])
        ->get(route('store.oauth.callback', [
            'platform' => 'shopify',
            'state' => 'invalid-state',
            'code' => 'test-code',
            'hmac' => 'test-hmac',
        ]));

    $response->assertRedirect(route('catalog.index'));
    $response->assertSessionHas('error');
});

test('oauth callback validates platform match', function () {
    $response = $this->actingAs($this->user)
        ->withSession([
            'store_oauth_state' => 'valid-state',
            'store_oauth_platform' => 'woocommerce',
            'store_oauth_store' => 'test-store.myshopify.com',
            'store_oauth_team_id' => $this->team->id,
        ])
        ->get(route('store.oauth.callback', [
            'platform' => 'shopify',
            'state' => 'valid-state',
            'code' => 'test-code',
        ]));

    $response->assertRedirect(route('catalog.index'));
    $response->assertSessionHas('error', 'Platform mismatch. Please try connecting again.');
});

test('oauth callback creates store connection on success', function () {
    Queue::fake();

    $hmacData = http_build_query(['code' => 'test-auth-code', 'shop' => 'test-store.myshopify.com', 'state' => 'valid-state']);
    $hmac = hash_hmac('sha256', $hmacData, 'test-client-secret');

    Http::fake([
        'test-store.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'shpat_test_token',
            'scope' => 'read_products,read_inventory',
        ]),
        'test-store.myshopify.com/admin/api/*/graphql.json' => Http::response([
            'data' => ['shop' => ['name' => 'Test Store']],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->withSession([
            'store_oauth_state' => 'valid-state',
            'store_oauth_platform' => 'shopify',
            'store_oauth_store' => 'test-store.myshopify.com',
            'store_oauth_team_id' => $this->team->id,
        ])
        ->get(route('store.oauth.callback', [
            'platform' => 'shopify',
            'state' => 'valid-state',
            'code' => 'test-auth-code',
            'shop' => 'test-store.myshopify.com',
            'hmac' => $hmac,
        ]));

    $response->assertRedirect(route('catalog.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('store_connections', [
        'team_id' => $this->team->id,
        'platform' => 'shopify',
        'store_identifier' => 'test-store.myshopify.com',
        'status' => StoreConnection::STATUS_CONNECTED,
    ]);

    Queue::assertPushed(SyncStoreProducts::class);
});

test('disconnect removes store connection', function () {
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

    $connection = StoreConnection::factory()->create([
        'team_id' => $this->team->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('store.disconnect', $connection));

    $response->assertRedirect(route('catalog.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseMissing('store_connections', ['id' => $connection->id]);
});

test('cannot disconnect another teams connection', function () {
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

    $otherTeam = \App\Models\Team::factory()->create();
    $connection = StoreConnection::factory()->create([
        'team_id' => $otherTeam->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('store.disconnect', $connection));

    $response->assertStatus(403);

    $this->assertDatabaseHas('store_connections', ['id' => $connection->id]);
});
