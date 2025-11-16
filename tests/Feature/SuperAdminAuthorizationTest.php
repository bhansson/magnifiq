<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_partners_page(): void
    {
        $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get('/admin/partners');

        $response->assertStatus(200);
        $response->assertSee('Partners');
    }

    public function test_superadmin_can_access_revenue_page(): void
    {
        $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get('/admin/revenue');

        $response->assertStatus(200);
        $response->assertSee('Partner Revenue');
    }

    public function test_regular_user_cannot_access_partners_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin/partners');

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_access_revenue_page(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin/revenue');

        $response->assertStatus(403);
    }

    public function test_admin_role_cannot_access_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/partners');
        $response->assertStatus(403);

        $response = $this->actingAs($admin)->get('/admin/revenue');
        $response->assertStatus(403);
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

    public function test_user_role_helper_methods(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        // Test isSuperAdmin()
        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertFalse($admin->isSuperAdmin());
        $this->assertFalse($user->isSuperAdmin());

        // Test isAdmin()
        $this->assertTrue($superadmin->isAdmin());
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());

        // Test hasRole()
        $this->assertTrue($superadmin->hasRole('superadmin'));
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_new_users_default_to_user_role(): void
    {
        $user = User::factory()->create();

        $this->assertEquals('user', $user->role);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_superadmin_sees_admin_links_in_navigation(): void
    {
        $superadmin = User::factory()->withPersonalTeam()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Partners');
        $response->assertSee('Revenue');
    }

    public function test_regular_user_does_not_see_admin_links_in_navigation(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('Partners', false);
        $response->assertDontSee('Revenue', false);
    }
}
