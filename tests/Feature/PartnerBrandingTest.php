<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('partner logo displays on login page with partner query', function () {
    Storage::fake('public');

    $partner = Team::factory()->create([
        'type' => 'partner',
        'name' => 'Acme Corp',
        'partner_slug' => 'acme',
        'logo_path' => 'partners/logos/acme.png',
    ]);

    // Create a fake file so the path exists
    Storage::disk('public')->put('partners/logos/acme.png', 'fake image content');

    $response = $this->get('/login?partner=acme');

    $response->assertStatus(200);
    $response->assertSee('storage/partners/logos/acme.png', false);
    $response->assertSee('Acme Corp');
});

test('default logo displays without partner query', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertDontSee('storage/partners/logos');
    $response->assertSee('<svg', false);
});

test('default logo displays with invalid partner slug', function () {
    $response = $this->get('/login?partner=nonexistent');

    $response->assertStatus(200);
    $response->assertDontSee('storage/partners/logos');
    $response->assertSee('<svg', false);
});