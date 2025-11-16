<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_logo_displays_on_login_page_with_partner_query(): void
    {
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
    }

    public function test_default_logo_displays_without_partner_query(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertDontSee('storage/partners/logos');
        $response->assertSee('<svg', false);
    }

    public function test_default_logo_displays_with_invalid_partner_slug(): void
    {
        $response = $this->get('/login?partner=nonexistent');

        $response->assertStatus(200);
        $response->assertDontSee('storage/partners/logos');
        $response->assertSee('<svg', false);
    }
}
