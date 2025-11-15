<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_can_have_logo_path(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'logo_path' => 'partners/logos/acme-corp.png',
        ]);

        $this->assertEquals('partners/logos/acme-corp.png', $partner->logo_path);
    }

    public function test_partner_can_have_custom_slug(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'partner_slug' => 'acme',
        ]);

        $this->assertEquals('acme', $partner->partner_slug);
    }

    public function test_partner_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'partner_slug' => 'acme',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'partner_slug' => 'acme', // Duplicate
        ]);
    }
}
