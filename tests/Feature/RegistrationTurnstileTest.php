<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Ensure Turnstile config is set for tests
    config(['turnstile.turnstile_secret_key' => 'test-secret-key']);
    config(['turnstile.turnstile_site_key' => 'test-site-key']);

    // Laravel 12 uses ValidateCsrfToken, need to disable both for POST tests
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);
});

test('registration requires valid turnstile token', function () {
    // Mock Cloudflare Turnstile API to reject the token
    Http::fake([
        'https://challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'cf-turnstile-response' => 'invalid-token',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('registration fails without turnstile token', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('registration succeeds with valid turnstile token', function () {
    Notification::fake();

    // Mock Cloudflare Turnstile API to accept the token
    Http::fake([
        'https://challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'challenge_ts' => now()->toIso8601String(),
            'hostname' => 'localhost',
        ]),
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'cf-turnstile-response' => 'valid-token',
    ]);

    // Should redirect to email verification or dashboard
    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'name' => 'Test User',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    // User should be unverified initially
    expect($user->email_verified_at)->toBeNull();

    // Verify that the user has a personal team
    expect($user->ownedTeams)->toHaveCount(1);
    expect($user->ownedTeams->first()->personal_team)->toBeTrue();
});

test('registration sends verification email', function () {
    Notification::fake();

    Http::fake([
        'https://challenges.cloudflare.com/*' => Http::response([
            'success' => true,
            'challenge_ts' => now()->toIso8601String(),
            'hostname' => 'localhost',
        ]),
    ]);

    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'cf-turnstile-response' => 'valid-token',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    Notification::assertSentTo($user, VerifyEmail::class);
});
