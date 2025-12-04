<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('email verification screen can be rendered', function () {
    $user = User::factory()->withPersonalTeam()->unverified()->create();

    $response = $this->actingAs($user)->get('/email/verify');

    $response->assertStatus(200);
});

test('unverified user is redirected to verification notice when accessing dashboard', function () {
    $user = User::factory()->withPersonalTeam()->unverified()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect('/email/verify');
});

test('verified user can access dashboard', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});

test('verification notification can be resent', function () {
    Notification::fake();

    // Laravel 12 requires disabling CSRF middleware for POST tests
    $this->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ]);

    $user = User::factory()->withPersonalTeam()->unverified()->create();

    $response = $this->actingAs($user)->post('/email/verification-notification');

    Notification::assertSentTo($user, VerifyEmail::class);
    $response->assertRedirect();
});

test('email can be verified', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('email can not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
