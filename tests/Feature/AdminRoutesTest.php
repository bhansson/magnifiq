<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_partners_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/admin/partners');

        $response->assertStatus(200);
        $response->assertSee('Partners');
    }

    public function test_authenticated_user_can_access_revenue_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/admin/revenue');

        $response->assertStatus(200);
        $response->assertSee('Partner Revenue');
    }

    public function test_guest_cannot_access_partners_page(): void
    {
        $response = $this->get('/admin/partners');

        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_access_revenue_page(): void
    {
        $response = $this->get('/admin/revenue');

        $response->assertRedirect('/login');
    }
}
