# Partner Whitelabel System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add partner (whitelabel) support allowing partners to own/manage multiple teams, earn revenue share, and provide custom branding at login/registration.

**Architecture:** Extend the existing team-based multi-tenancy model by adding a `type` column to teams (default: `customer`, new: `partner`). Partners can "own" other teams via a new `parent_team_id` foreign key. Admin dashboard tracks partner usage/revenue. Custom branding (logo) stored per partner team and displayed on auth pages via URL parameter or subdomain detection (phase 2).

**Tech Stack:** Laravel 12, Jetstream (Livewire), Octane/Swoole, Tailwind CSS, Alpine.js, Supabase Postgres

---

## Phase 1: Database Schema & Models

### Task 1: Add Team Type and Parent Relationship

**Files:**
- Create: `database/migrations/2025_11_15_000001_add_partner_fields_to_teams_table.php`
- Modify: `app/Models/Team.php`
- Create: `tests/Unit/TeamTypeTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/TeamTypeTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_has_customer_type_by_default(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->assertEquals('customer', $team->type);
    }

    public function test_team_can_be_partner_type(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
        ]);

        $this->assertEquals('partner', $team->type);
        $this->assertTrue($team->isPartner());
    }

    public function test_team_can_have_parent_team(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
        ]);
        $customer = Team::factory()->create([
            'user_id' => $user->id,
            'parent_team_id' => $partner->id,
        ]);

        $this->assertEquals($partner->id, $customer->parent_team_id);
        $this->assertTrue($customer->parentTeam->is($partner));
    }

    public function test_partner_can_have_many_owned_teams(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
        ]);
        $team1 = Team::factory()->create([
            'user_id' => $user->id,
            'parent_team_id' => $partner->id,
        ]);
        $team2 = Team::factory()->create([
            'user_id' => $user->id,
            'parent_team_id' => $partner->id,
        ]);

        $this->assertCount(2, $partner->ownedTeams);
        $this->assertTrue($partner->ownedTeams->contains($team1));
        $this->assertTrue($partner->ownedTeams->contains($team2));
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=TeamTypeTest
```

Expected: FAIL with "Unknown column 'type'" and "Unknown column 'parent_team_id'"

**Step 3: Create migration**

Create `database/migrations/2025_11_15_000001_add_partner_fields_to_teams_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('type', 20)->default('customer')->after('personal_team');
            $table->foreignId('parent_team_id')->nullable()->after('user_id')->constrained('teams')->nullOnDelete();
            $table->index(['type', 'parent_team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['parent_team_id']);
            $table->dropColumn(['type', 'parent_team_id']);
        });
    }
};
```

**Step 4: Run migration**

Run:
```bash
docker compose exec octane php artisan migrate
```

Expected: Migration runs successfully

**Step 5: Update Team model**

Modify `app/Models/Team.php`:

```php
// Add to $fillable array:
protected $fillable = [
    'name',
    'public_hash',
    'personal_team',
    'type',           // ADD THIS
    'parent_team_id', // ADD THIS
];

// Add after products() method:
public function isPartner(): bool
{
    return $this->type === 'partner';
}

public function isCustomer(): bool
{
    return $this->type === 'customer';
}

public function parentTeam()
{
    return $this->belongsTo(Team::class, 'parent_team_id');
}

public function ownedTeams()
{
    return $this->hasMany(Team::class, 'parent_team_id');
}
```

**Step 6: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=TeamTypeTest
```

Expected: All tests PASS

**Step 7: Commit**

```bash
git add database/migrations/2025_11_15_000001_add_partner_fields_to_teams_table.php app/Models/Team.php tests/Unit/TeamTypeTest.php
git commit -m "feat: add team type and parent relationship for partners"
```

---

### Task 2: Add Partner Branding Fields

**Files:**
- Create: `database/migrations/2025_11_15_000002_add_branding_fields_to_teams_table.php`
- Modify: `app/Models/Team.php`
- Create: `tests/Unit/TeamBrandingTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/TeamBrandingTest.php`:

```php
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
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=TeamBrandingTest
```

Expected: FAIL with "Unknown column 'logo_path'" and "Unknown column 'partner_slug'"

**Step 3: Create migration**

Create `database/migrations/2025_11_15_000002_add_branding_fields_to_teams_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('logo_path', 2048)->nullable()->after('type');
            $table->string('partner_slug', 50)->nullable()->unique()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'partner_slug']);
        });
    }
};
```

**Step 4: Run migration**

Run:
```bash
docker compose exec octane php artisan migrate
```

Expected: Migration runs successfully

**Step 5: Update Team model**

Modify `app/Models/Team.php`:

```php
// Add to $fillable array:
protected $fillable = [
    'name',
    'public_hash',
    'personal_team',
    'type',
    'parent_team_id',
    'logo_path',      // ADD THIS
    'partner_slug',   // ADD THIS
];
```

**Step 6: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=TeamBrandingTest
```

Expected: All tests PASS

**Step 7: Commit**

```bash
git add database/migrations/2025_11_15_000002_add_branding_fields_to_teams_table.php app/Models/Team.php tests/Unit/TeamBrandingTest.php
git commit -m "feat: add branding fields (logo, slug) for partners"
```

---

### Task 3: Add Revenue Tracking Table

**Files:**
- Create: `database/migrations/2025_11_15_000003_create_partner_revenue_table.php`
- Create: `app/Models/PartnerRevenue.php`
- Create: `tests/Unit/PartnerRevenueTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/PartnerRevenueTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_partner_revenue_record(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
        $customer = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

        $revenue = PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000, // $100
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 2000, // $20
            'currency' => 'USD',
        ]);

        $this->assertDatabaseHas('partner_revenue', [
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer->id,
            'customer_revenue_cents' => 10000,
            'partner_revenue_cents' => 2000,
        ]);
    }

    public function test_partner_has_many_revenue_records(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
        $customer1 = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);
        $customer2 = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

        PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer1->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 2000,
            'currency' => 'USD',
        ]);

        PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer2->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 5000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 1000,
            'currency' => 'USD',
        ]);

        $this->assertCount(2, $partner->revenueRecords);
    }

    public function test_revenue_amounts_are_cast_to_integers(): void
    {
        $user = User::factory()->create();
        $partner = Team::factory()->create(['user_id' => $user->id, 'type' => 'partner']);
        $customer = Team::factory()->create(['user_id' => $user->id, 'parent_team_id' => $partner->id]);

        $revenue = PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => '10000',
            'partner_share_percent' => '20.00',
            'partner_revenue_cents' => '2000',
            'currency' => 'USD',
        ]);

        $this->assertIsInt($revenue->customer_revenue_cents);
        $this->assertIsInt($revenue->partner_revenue_cents);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerRevenueTest
```

Expected: FAIL with "Class 'App\Models\PartnerRevenue' not found"

**Step 3: Create migration**

Create `database/migrations/2025_11_15_000003_create_partner_revenue_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_revenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('customer_team_id')->constrained('teams')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('customer_revenue_cents'); // Customer's total revenue
            $table->decimal('partner_share_percent', 5, 2); // e.g., 20.00%
            $table->unsignedBigInteger('partner_revenue_cents'); // Partner's share
            $table->string('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['partner_team_id', 'period_start', 'period_end']);
            $table->index('customer_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_revenue');
    }
};
```

**Step 4: Run migration**

Run:
```bash
docker compose exec octane php artisan migrate
```

Expected: Migration runs successfully

**Step 5: Create PartnerRevenue model**

Create `app/Models/PartnerRevenue.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerRevenue extends Model
{
    use HasFactory;

    protected $table = 'partner_revenue';

    protected $fillable = [
        'partner_team_id',
        'customer_team_id',
        'period_start',
        'period_end',
        'customer_revenue_cents',
        'partner_share_percent',
        'partner_revenue_cents',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'customer_revenue_cents' => 'integer',
            'partner_revenue_cents' => 'integer',
            'partner_share_percent' => 'decimal:2',
        ];
    }

    public function partnerTeam()
    {
        return $this->belongsTo(Team::class, 'partner_team_id');
    }

    public function customerTeam()
    {
        return $this->belongsTo(Team::class, 'customer_team_id');
    }
}
```

**Step 6: Update Team model relationships**

Modify `app/Models/Team.php`:

```php
// Add after ownedTeams() method:
public function revenueRecords()
{
    return $this->hasMany(PartnerRevenue::class, 'partner_team_id');
}

public function revenueAsCustomer()
{
    return $this->hasMany(PartnerRevenue::class, 'customer_team_id');
}
```

**Step 7: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerRevenueTest
```

Expected: All tests PASS

**Step 8: Commit**

```bash
git add database/migrations/2025_11_15_000003_create_partner_revenue_table.php app/Models/PartnerRevenue.php app/Models/Team.php tests/Unit/PartnerRevenueTest.php
git commit -m "feat: add partner revenue tracking table and model"
```

---

## Phase 2: Authorization & Policies

### Task 4: Update Team Policy for Partner Access

**Files:**
- Modify: `app/Policies/TeamPolicy.php`
- Create: `tests/Feature/PartnerTeamAccessTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/PartnerTeamAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_owner_can_view_owned_teams(): void
    {
        $partnerOwner = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $partnerOwner->id,
            'type' => 'partner',
        ]);

        $customerOwner = User::factory()->create();
        $customer = Team::factory()->create([
            'user_id' => $customerOwner->id,
            'parent_team_id' => $partner->id,
        ]);

        // Add partner owner to customer team
        $customer->users()->attach($partnerOwner, ['role' => 'partner_admin']);

        $this->assertTrue($partnerOwner->can('view', $customer));
    }

    public function test_partner_member_can_view_owned_teams(): void
    {
        $partnerOwner = User::factory()->create();
        $partnerMember = User::factory()->create();

        $partner = Team::factory()->create([
            'user_id' => $partnerOwner->id,
            'type' => 'partner',
        ]);

        // Add member to partner team
        $partner->users()->attach($partnerMember, ['role' => 'admin']);

        $customerOwner = User::factory()->create();
        $customer = Team::factory()->create([
            'user_id' => $customerOwner->id,
            'parent_team_id' => $partner->id,
        ]);

        // Add partner member to customer team
        $customer->users()->attach($partnerMember, ['role' => 'partner_admin']);

        $this->assertTrue($partnerMember->can('view', $customer));
    }

    public function test_non_partner_cannot_view_unrelated_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->assertFalse($user->can('view', $team));
    }

    public function test_partner_can_update_owned_team_settings(): void
    {
        $partnerOwner = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $partnerOwner->id,
            'type' => 'partner',
        ]);

        $customerOwner = User::factory()->create();
        $customer = Team::factory()->create([
            'user_id' => $customerOwner->id,
            'parent_team_id' => $partner->id,
        ]);

        $customer->users()->attach($partnerOwner, ['role' => 'partner_admin']);

        // Partner should be able to update customer team
        $this->assertTrue($partnerOwner->can('update', $customer));
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerTeamAccessTest
```

Expected: FAIL - authorization policies don't yet support partner access

**Step 3: Update TeamPolicy**

Modify `app/Policies/TeamPolicy.php`:

```php
// Update the view() method:
public function view(User $user, Team $team): bool
{
    // Original logic: user is member
    if ($user->belongsToTeam($team)) {
        return true;
    }

    // NEW: Check if user belongs to parent partner team
    if ($team->parent_team_id) {
        $parentTeam = $team->parentTeam;
        if ($parentTeam && $user->belongsToTeam($parentTeam)) {
            return true;
        }
    }

    return false;
}

// Update the update() method:
public function update(User $user, Team $team): bool
{
    // Original logic: user owns team
    if ($user->ownsTeam($team)) {
        return true;
    }

    // NEW: Check if user owns parent partner team
    if ($team->parent_team_id) {
        $parentTeam = $team->parentTeam;
        if ($parentTeam && $user->ownsTeam($parentTeam)) {
            return true;
        }
    }

    return false;
}

// Add new helper method at end of class:
protected function userBelongsToParentPartner(User $user, Team $team): bool
{
    if (! $team->parent_team_id) {
        return false;
    }

    $parentTeam = $team->parentTeam;

    return $parentTeam && $user->belongsToTeam($parentTeam);
}
```

**Step 4: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerTeamAccessTest
```

Expected: All tests PASS

**Step 5: Commit**

```bash
git add app/Policies/TeamPolicy.php tests/Feature/PartnerTeamAccessTest.php
git commit -m "feat: update team policy to allow partner access to owned teams"
```

---

## Phase 3: Partner Management UI

### Task 5: Create Partner Management Livewire Component

**Files:**
- Create: `app/Livewire/ManagePartners.php`
- Create: `resources/views/livewire/manage-partners.blade.php`
- Create: `tests/Feature/ManagePartnersTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/ManagePartnersTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagePartnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_partners_list(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner1 = Team::factory()->create(['type' => 'partner', 'name' => 'Acme Partner']);
        $partner2 = Team::factory()->create(['type' => 'partner', 'name' => 'Beta Partner']);
        $customer = Team::factory()->create(['type' => 'customer']);

        Livewire::test(ManagePartners::class)
            ->assertSee('Acme Partner')
            ->assertSee('Beta Partner')
            ->assertDontSee($customer->name);
    }

    public function test_can_create_new_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ManagePartners::class)
            ->set('name', 'New Partner Inc')
            ->set('partner_slug', 'newpartner')
            ->set('partner_share_percent', 25.00)
            ->call('createPartner')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teams', [
            'name' => 'New Partner Inc',
            'type' => 'partner',
            'partner_slug' => 'newpartner',
        ]);
    }

    public function test_partner_name_is_required(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ManagePartners::class)
            ->set('name', '')
            ->set('partner_slug', 'test')
            ->call('createPartner')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_partner_slug_must_be_unique(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Team::factory()->create([
            'type' => 'partner',
            'partner_slug' => 'acme',
        ]);

        Livewire::test(ManagePartners::class)
            ->set('name', 'Another Partner')
            ->set('partner_slug', 'acme')
            ->call('createPartner')
            ->assertHasErrors(['partner_slug' => 'unique']);
    }

    public function test_can_delete_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Delete Me']);

        Livewire::test(ManagePartners::class)
            ->call('deletePartner', $partner->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('teams', [
            'id' => $partner->id,
        ]);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=ManagePartnersTest
```

Expected: FAIL with "Class 'App\Livewire\ManagePartners' not found"

**Step 3: Create Livewire component**

Create `app/Livewire/ManagePartners.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class ManagePartners extends Component
{
    use WithPagination;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50|unique:teams,partner_slug')]
    public string $partner_slug = '';

    #[Validate('nullable|numeric|min:0|max:100')]
    public ?float $partner_share_percent = 20.00;

    public bool $showCreateModal = false;

    public function createPartner(): void
    {
        $this->validate();

        Team::create([
            'user_id' => Auth::id(),
            'name' => $this->name,
            'type' => 'partner',
            'partner_slug' => $this->partner_slug ?: null,
            'personal_team' => false,
        ]);

        $this->reset(['name', 'partner_slug', 'partner_share_percent', 'showCreateModal']);
        $this->resetPage();

        session()->flash('message', 'Partner created successfully.');
    }

    public function deletePartner(int $partnerId): void
    {
        $partner = Team::query()
            ->where('type', 'partner')
            ->findOrFail($partnerId);

        $partner->delete();

        session()->flash('message', 'Partner deleted successfully.');
    }

    public function render()
    {
        $partners = Team::query()
            ->where('type', 'partner')
            ->withCount('ownedTeams')
            ->latest()
            ->paginate(15);

        return view('livewire.manage-partners', [
            'partners' => $partners,
        ]);
    }
}
```

**Step 4: Create Blade view**

Create `resources/views/livewire/manage-partners.blade.php`:

```blade
<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-800">Partners</h2>
        <button
            wire:click="$set('showCreateModal', true)"
            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
        >
            Create Partner
        </button>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-md">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owned Teams</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($partners as $partner)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $partner->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->partner_slug ?: '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->owned_teams_count }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $partner->created_at->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button
                                wire:click="deletePartner({{ $partner->id }})"
                                wire:confirm="Are you sure you want to delete this partner?"
                                class="text-red-600 hover:text-red-900"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            No partners found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $partners->links() }}
    </div>

    <!-- Create Partner Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Create New Partner</h3>

                <form wire:submit="createPartner">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Partner Name</label>
                        <input
                            type="text"
                            id="name"
                            wire:model="name"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_slug" class="block text-sm font-medium text-gray-700">Slug (optional)</label>
                        <input
                            type="text"
                            id="partner_slug"
                            wire:model="partner_slug"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('partner_slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-4">
                        <label for="partner_share_percent" class="block text-sm font-medium text-gray-700">Revenue Share %</label>
                        <input
                            type="number"
                            step="0.01"
                            id="partner_share_percent"
                            wire:model="partner_share_percent"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                        />
                        @error('partner_share_percent') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            wire:click="$set('showCreateModal', false)"
                            class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                        >
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=ManagePartnersTest
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Livewire/ManagePartners.php resources/views/livewire/manage-partners.blade.php tests/Feature/ManagePartnersTest.php
git commit -m "feat: add partner management UI component"
```

---

### Task 6: Add Partner Revenue Dashboard Component

**Files:**
- Create: `app/Livewire/PartnerRevenueDashboard.php`
- Create: `resources/views/livewire/partner-revenue-dashboard.blade.php`
- Create: `tests/Feature/PartnerRevenueDashboardTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/PartnerRevenueDashboardTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Livewire\PartnerRevenueDashboard;
use App\Models\PartnerRevenue;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerRevenueDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_partner_revenue_records(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Acme']);
        $customer = Team::factory()->create(['parent_team_id' => $partner->id]);

        PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 2000,
            'currency' => 'USD',
        ]);

        Livewire::test(PartnerRevenueDashboard::class)
            ->assertSee('Acme')
            ->assertSee('$100.00') // customer revenue
            ->assertSee('$20.00'); // partner revenue
    }

    public function test_filters_by_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner1 = Team::factory()->create(['type' => 'partner', 'name' => 'Partner A']);
        $partner2 = Team::factory()->create(['type' => 'partner', 'name' => 'Partner B']);

        $customer1 = Team::factory()->create(['parent_team_id' => $partner1->id]);
        $customer2 = Team::factory()->create(['parent_team_id' => $partner2->id]);

        PartnerRevenue::create([
            'partner_team_id' => $partner1->id,
            'customer_team_id' => $customer1->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 2000,
            'currency' => 'USD',
        ]);

        PartnerRevenue::create([
            'partner_team_id' => $partner2->id,
            'customer_team_id' => $customer2->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 5000,
            'partner_share_percent' => 15.00,
            'partner_revenue_cents' => 750,
            'currency' => 'USD',
        ]);

        Livewire::test(PartnerRevenueDashboard::class)
            ->set('selectedPartnerId', $partner1->id)
            ->assertSee('Partner A')
            ->assertSee('$20.00')
            ->assertDontSee('$7.50'); // partner2's revenue
    }

    public function test_displays_total_revenue_for_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner']);
        $customer1 = Team::factory()->create(['parent_team_id' => $partner->id]);
        $customer2 = Team::factory()->create(['parent_team_id' => $partner->id]);

        PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer1->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 10000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 2000,
            'currency' => 'USD',
        ]);

        PartnerRevenue::create([
            'partner_team_id' => $partner->id,
            'customer_team_id' => $customer2->id,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'customer_revenue_cents' => 5000,
            'partner_share_percent' => 20.00,
            'partner_revenue_cents' => 1000,
            'currency' => 'USD',
        ]);

        Livewire::test(PartnerRevenueDashboard::class)
            ->set('selectedPartnerId', $partner->id)
            ->assertSee('$30.00'); // Total partner revenue: $20 + $10
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerRevenueDashboardTest
```

Expected: FAIL with "Class 'App\Livewire\PartnerRevenueDashboard' not found"

**Step 3: Create Livewire component**

Create `app/Livewire/PartnerRevenueDashboard.php`:

```php
<?php

namespace App\Livewire;

use App\Models\PartnerRevenue;
use App\Models\Team;
use Livewire\Component;
use Livewire\WithPagination;

class PartnerRevenueDashboard extends Component
{
    use WithPagination;

    public ?int $selectedPartnerId = null;

    public function render()
    {
        $partners = Team::query()
            ->where('type', 'partner')
            ->orderBy('name')
            ->get();

        $revenueQuery = PartnerRevenue::query()
            ->with(['partnerTeam', 'customerTeam'])
            ->latest('period_start');

        if ($this->selectedPartnerId) {
            $revenueQuery->where('partner_team_id', $this->selectedPartnerId);
        }

        $revenueRecords = $revenueQuery->paginate(20);

        $totalPartnerRevenue = PartnerRevenue::query()
            ->when($this->selectedPartnerId, fn($q) => $q->where('partner_team_id', $this->selectedPartnerId))
            ->sum('partner_revenue_cents');

        return view('livewire.partner-revenue-dashboard', [
            'partners' => $partners,
            'revenueRecords' => $revenueRecords,
            'totalPartnerRevenue' => $totalPartnerRevenue,
        ]);
    }
}
```

**Step 4: Create Blade view**

Create `resources/views/livewire/partner-revenue-dashboard.blade.php`:

```blade
<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-800">Partner Revenue</h2>

        <div>
            <select wire:model.live="selectedPartnerId" class="border-gray-300 rounded-md shadow-sm">
                <option value="">All Partners</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-6 bg-white shadow rounded-lg p-6">
        <div class="text-sm text-gray-600">Total Partner Revenue</div>
        <div class="text-3xl font-bold text-gray-900">
            ${{ number_format($totalPartnerRevenue / 100, 2) }}
        </div>
    </div>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Share %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Revenue</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse ($revenueRecords as $record)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            {{ $record->partnerTeam->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $record->customerTeam->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $record->period_start->format('Y-m-d') }} — {{ $record->period_end->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            ${{ number_format($record->customer_revenue_cents / 100, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                            {{ $record->partner_share_percent }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 text-right">
                            ${{ number_format($record->partner_revenue_cents / 100, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                            No revenue records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $revenueRecords->links() }}
    </div>
</div>
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerRevenueDashboardTest
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Livewire/PartnerRevenueDashboard.php resources/views/livewire/partner-revenue-dashboard.blade.php tests/Feature/PartnerRevenueDashboardTest.php
git commit -m "feat: add partner revenue dashboard component"
```

---

### Task 7: Create Admin Routes for Partner Management

**Files:**
- Modify: `routes/web.php`
- Create: `resources/views/admin/partners.blade.php`
- Create: `resources/views/admin/revenue.blade.php`
- Create: `tests/Feature/AdminRoutesTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/AdminRoutesTest.php`:

```php
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
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/partners');

        $response->assertStatus(200);
        $response->assertSeeLivewire('manage-partners');
    }

    public function test_authenticated_user_can_access_revenue_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/revenue');

        $response->assertStatus(200);
        $response->assertSeeLivewire('partner-revenue-dashboard');
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
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=AdminRoutesTest
```

Expected: FAIL with 404 errors for admin routes

**Step 3: Add routes**

Modify `routes/web.php` - add after existing authenticated routes:

```php
// Partner admin routes
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/admin/partners', function () {
        return view('admin.partners');
    })->name('admin.partners');

    Route::get('/admin/revenue', function () {
        return view('admin.revenue');
    })->name('admin.revenue');
});
```

**Step 4: Create admin views**

Create `resources/views/admin/partners.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Partner Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('manage-partners')
        </div>
    </div>
</x-app-layout>
```

Create `resources/views/admin/revenue.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Partner Revenue') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire('partner-revenue-dashboard')
        </div>
    </div>
</x-app-layout>
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=AdminRoutesTest
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add routes/web.php resources/views/admin/partners.blade.php resources/views/admin/revenue.blade.php tests/Feature/AdminRoutesTest.php
git commit -m "feat: add admin routes for partner and revenue management"
```

---

## Phase 4: Whitelabel Branding

### Task 8: Add Partner Logo Upload

**Files:**
- Modify: `app/Livewire/ManagePartners.php`
- Modify: `resources/views/livewire/manage-partners.blade.php`
- Create: `tests/Feature/PartnerLogoUploadTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/PartnerLogoUploadTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerLogoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_partner_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $this->actingAs($admin);

        $logo = UploadedFile::fake()->image('partner-logo.png', 200, 200);

        Livewire::test(ManagePartners::class)
            ->set('name', 'Acme Corp')
            ->set('partner_slug', 'acme')
            ->set('logo', $logo)
            ->call('createPartner')
            ->assertHasNoErrors();

        $partner = Team::where('partner_slug', 'acme')->first();

        $this->assertNotNull($partner->logo_path);
        Storage::disk('public')->assertExists($partner->logo_path);
    }

    public function test_logo_must_be_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $this->actingAs($admin);

        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        Livewire::test(ManagePartners::class)
            ->set('name', 'Acme Corp')
            ->set('partner_slug', 'acme')
            ->set('logo', $invalidFile)
            ->call('createPartner')
            ->assertHasErrors(['logo']);
    }

    public function test_logo_must_not_exceed_2mb(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $this->actingAs($admin);

        $largeLogo = UploadedFile::fake()->image('large-logo.png')->size(3000); // 3MB

        Livewire::test(ManagePartners::class)
            ->set('name', 'Acme Corp')
            ->set('partner_slug', 'acme')
            ->set('logo', $largeLogo)
            ->call('createPartner')
            ->assertHasErrors(['logo']);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerLogoUploadTest
```

Expected: FAIL - logo upload functionality doesn't exist yet

**Step 3: Update ManagePartners component**

Modify `app/Livewire/ManagePartners.php`:

```php
// Add at top with other use statements:
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class ManagePartners extends Component
{
    use WithPagination;
    use WithFileUploads; // ADD THIS

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50|unique:teams,partner_slug')]
    public string $partner_slug = '';

    #[Validate('nullable|numeric|min:0|max:100')]
    public ?float $partner_share_percent = 20.00;

    #[Validate('nullable|image|max:2048')] // ADD THIS
    public $logo; // ADD THIS

    public bool $showCreateModal = false;

    public function createPartner(): void
    {
        $this->validate();

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('partners/logos', 'public');
        }

        Team::create([
            'user_id' => Auth::id(),
            'name' => $this->name,
            'type' => 'partner',
            'partner_slug' => $this->partner_slug ?: null,
            'logo_path' => $logoPath, // ADD THIS
            'personal_team' => false,
        ]);

        $this->reset(['name', 'partner_slug', 'partner_share_percent', 'logo', 'showCreateModal']);
        $this->resetPage();

        session()->flash('message', 'Partner created successfully.');
    }

    // ... rest of methods unchanged
}
```

**Step 4: Update Blade view**

Modify `resources/views/livewire/manage-partners.blade.php` - add logo field to the form:

```blade
<!-- Add after partner_slug field in the modal -->
<div class="mb-4">
    <label for="logo" class="block text-sm font-medium text-gray-700">Logo (optional)</label>
    <input
        type="file"
        id="logo"
        wire:model="logo"
        accept="image/*"
        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
    />
    @error('logo') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror

    @if ($logo)
        <div class="mt-2">
            <img src="{{ $logo->temporaryUrl() }}" class="h-20 w-auto" alt="Logo preview">
        </div>
    @endif
</div>
```

Also update the table to show logo:

```blade
<!-- Add new column header after Name -->
<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>

<!-- Add new cell in tbody after name -->
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
    @if ($partner->logo_path)
        <img src="{{ Storage::disk('public')->url($partner->logo_path) }}" class="h-10 w-auto" alt="{{ $partner->name }} logo">
    @else
        —
    @endif
</td>
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerLogoUploadTest
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Livewire/ManagePartners.php resources/views/livewire/manage-partners.blade.php tests/Feature/PartnerLogoUploadTest.php
git commit -m "feat: add partner logo upload functionality"
```

---

### Task 9: Display Partner Logo on Auth Pages

**Files:**
- Modify: `resources/views/components/authentication-card-logo.blade.php`
- Modify: `app/Http/Middleware/DetectPartnerContext.php` (create)
- Modify: `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 12)
- Create: `tests/Feature/PartnerBrandingTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/PartnerBrandingTest.php`:

```php
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

    public function test_partner_logo_displays_on_login_page_with_slug(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'partner_slug' => 'acme',
            'logo_path' => 'partners/logos/acme.png',
        ]);

        Storage::disk('public')->put('partners/logos/acme.png', 'fake-logo-content');

        $response = $this->get('/login?partner=acme');

        $response->assertStatus(200);
        $response->assertSee('partners/logos/acme.png', false);
    }

    public function test_default_logo_displays_without_partner_slug(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        // Should show default SVG logo
        $response->assertSee('<svg', false);
    }

    public function test_partner_logo_displays_on_register_page(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $partner = Team::factory()->create([
            'user_id' => $user->id,
            'type' => 'partner',
            'partner_slug' => 'beta',
            'logo_path' => 'partners/logos/beta.png',
        ]);

        Storage::disk('public')->put('partners/logos/beta.png', 'fake-logo-content');

        $response = $this->get('/register?partner=beta');

        $response->assertStatus(200);
        $response->assertSee('partners/logos/beta.png', false);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerBrandingTest
```

Expected: FAIL - partner branding not yet implemented

**Step 3: Create middleware to detect partner context**

Create `app/Http/Middleware/DetectPartnerContext.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectPartnerContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $partnerSlug = $request->query('partner');

        if ($partnerSlug) {
            $partner = Team::query()
                ->where('type', 'partner')
                ->where('partner_slug', $partnerSlug)
                ->first();

            if ($partner) {
                $request->attributes->set('partner', $partner);
                view()->share('partner', $partner);
            }
        }

        return $next($request);
    }
}
```

**Step 4: Register middleware**

For Laravel 12, modify `bootstrap/app.php`:

```php
// Add after Application::configure():
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\DetectPartnerContext::class,
    ]);
})
```

**Step 5: Update authentication card logo component**

Modify `resources/views/components/authentication-card-logo.blade.php`:

```blade
<a href="/">
    @if (isset($partner) && $partner->logo_path)
        <img
            src="{{ Storage::disk('public')->url($partner->logo_path) }}"
            alt="{{ $partner->name }}"
            class="h-16 w-auto"
        />
    @else
        <svg class="size-16" viewbox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11.395 44.428C4.557 40.198 0 32.632 0 24 0 10.745 10.745 0 24 0a23.891 23.891 0 0113.997 4.502c-.2 17.907-11.097 33.245-26.602 39.926z" fill="#6875F5"/>
            <path d="M14.134 45.885A23.914 23.914 0 0024 48c13.255 0 24-10.745 24-24 0-3.516-.756-6.856-2.115-9.866-4.659 15.143-16.608 27.092-31.75 31.751z" fill="#6875F5"/>
        </svg>
    @endif
</a>
```

**Step 6: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=PartnerBrandingTest
```

Expected: All tests PASS

**Step 7: Reload Octane**

Run:
```bash
docker compose exec octane php artisan octane:reload
```

**Step 8: Commit**

```bash
git add app/Http/Middleware/DetectPartnerContext.php bootstrap/app.php resources/views/components/authentication-card-logo.blade.php tests/Feature/PartnerBrandingTest.php
git commit -m "feat: display partner logo on auth pages via query parameter"
```

---

## Phase 5: Partner Team Assignment

### Task 10: Assign Teams to Partners

**Files:**
- Modify: `app/Livewire/ManagePartners.php`
- Modify: `resources/views/livewire/manage-partners.blade.php`
- Create: `tests/Feature/AssignTeamsToPartnerTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/AssignTeamsToPartnerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Livewire\ManagePartners;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssignTeamsToPartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_assign_customer_team_to_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner']);
        $customer = Team::factory()->create(['type' => 'customer', 'parent_team_id' => null]);

        Livewire::test(ManagePartners::class)
            ->call('assignTeamToPartner', $customer->id, $partner->id)
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertEquals($partner->id, $customer->parent_team_id);
    }

    public function test_can_unassign_team_from_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner']);
        $customer = Team::factory()->create(['type' => 'customer', 'parent_team_id' => $partner->id]);

        Livewire::test(ManagePartners::class)
            ->call('unassignTeamFromPartner', $customer->id)
            ->assertHasNoErrors();

        $customer->refresh();
        $this->assertNull($customer->parent_team_id);
    }

    public function test_displays_owned_teams_for_partner(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $partner = Team::factory()->create(['type' => 'partner', 'name' => 'Partner X']);
        $customer1 = Team::factory()->create(['type' => 'customer', 'name' => 'Customer A', 'parent_team_id' => $partner->id]);
        $customer2 = Team::factory()->create(['type' => 'customer', 'name' => 'Customer B', 'parent_team_id' => $partner->id]);

        Livewire::test(ManagePartners::class)
            ->set('viewingPartnerId', $partner->id)
            ->assertSee('Customer A')
            ->assertSee('Customer B');
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=AssignTeamsToPartnerTest
```

Expected: FAIL - team assignment methods don't exist yet

**Step 3: Update ManagePartners component**

Modify `app/Livewire/ManagePartners.php`:

```php
// Add new properties:
public ?int $viewingPartnerId = null;
public bool $showAssignModal = false;
public ?int $assigningPartnerId = null;

// Add new methods:
public function viewOwnedTeams(int $partnerId): void
{
    $this->viewingPartnerId = $partnerId;
}

public function closeOwnedTeamsView(): void
{
    $this->viewingPartnerId = null;
}

public function openAssignModal(int $partnerId): void
{
    $this->assigningPartnerId = $partnerId;
    $this->showAssignModal = true;
}

public function assignTeamToPartner(int $teamId, int $partnerId): void
{
    $team = Team::findOrFail($teamId);
    $partner = Team::query()
        ->where('type', 'partner')
        ->findOrFail($partnerId);

    $team->update(['parent_team_id' => $partner->id]);

    session()->flash('message', 'Team assigned successfully.');
}

public function unassignTeamFromPartner(int $teamId): void
{
    $team = Team::findOrFail($teamId);
    $team->update(['parent_team_id' => null]);

    session()->flash('message', 'Team unassigned successfully.');
}

// Update render method:
public function render()
{
    $partners = Team::query()
        ->where('type', 'partner')
        ->withCount('ownedTeams')
        ->latest()
        ->paginate(15);

    $ownedTeams = null;
    if ($this->viewingPartnerId) {
        $ownedTeams = Team::query()
            ->where('parent_team_id', $this->viewingPartnerId)
            ->get();
    }

    $availableTeams = Team::query()
        ->where('type', 'customer')
        ->whereNull('parent_team_id')
        ->get();

    return view('livewire.manage-partners', [
        'partners' => $partners,
        'ownedTeams' => $ownedTeams,
        'availableTeams' => $availableTeams,
    ]);
}
```

**Step 4: Update Blade view**

Modify `resources/views/livewire/manage-partners.blade.php` - add "View Teams" action:

```blade
<!-- In the actions column, add before Delete button -->
<button
    wire:click="viewOwnedTeams({{ $partner->id }})"
    class="text-indigo-600 hover:text-indigo-900 mr-4"
>
    View Teams ({{ $partner->owned_teams_count }})
</button>
```

Add owned teams modal at the end:

```blade
<!-- Owned Teams Modal -->
@if ($viewingPartnerId && $ownedTeams)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full max-h-96 overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Owned Teams</h3>
                <button
                    wire:click="closeOwnedTeamsView"
                    class="text-gray-400 hover:text-gray-600"
                >
                    ✕
                </button>
            </div>

            <div class="space-y-2">
                @forelse ($ownedTeams as $team)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <span>{{ $team->name }}</span>
                        <button
                            wire:click="unassignTeamFromPartner({{ $team->id }})"
                            wire:confirm="Remove this team from the partner?"
                            class="text-red-600 hover:text-red-900 text-sm"
                        >
                            Unassign
                        </button>
                    </div>
                @empty
                    <p class="text-gray-500">No teams assigned to this partner.</p>
                @endforelse
            </div>

            <div class="mt-4">
                <button
                    wire:click="openAssignModal({{ $viewingPartnerId }})"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
                >
                    Assign Team
                </button>
            </div>
        </div>
    </div>
@endif

<!-- Assign Team Modal -->
@if ($showAssignModal && $assigningPartnerId)
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
            <h3 class="text-lg font-semibold mb-4">Assign Team to Partner</h3>

            <div class="space-y-2 max-h-64 overflow-y-auto">
                @forelse ($availableTeams as $team)
                    <button
                        wire:click="assignTeamToPartner({{ $team->id }}, {{ $assigningPartnerId }}); $set('showAssignModal', false)"
                        class="w-full text-left p-3 bg-gray-50 hover:bg-gray-100 rounded"
                    >
                        {{ $team->name }}
                    </button>
                @empty
                    <p class="text-gray-500">No unassigned teams available.</p>
                @endforelse
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    wire:click="$set('showAssignModal', false)"
                    class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
@endif
```

**Step 5: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=AssignTeamsToPartnerTest
```

Expected: All tests PASS

**Step 6: Commit**

```bash
git add app/Livewire/ManagePartners.php resources/views/livewire/manage-partners.blade.php tests/Feature/AssignTeamsToPartnerTest.php
git commit -m "feat: add team assignment/unassignment for partners"
```

---

## Phase 6: Integration & Documentation

### Task 11: Add Navigation Links for Admin

**Files:**
- Modify: `resources/views/navigation-menu.blade.php`
- Create: `tests/Feature/AdminNavigationTest.php`

**Step 1: Write the failing test**

Create `tests/Feature/AdminNavigationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_admin_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Partners');
        $response->assertSee('Revenue');
    }

    public function test_guest_does_not_see_admin_links(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('Partners', false);
        $response->assertDontSee('Revenue', false);
    }
}
```

**Step 2: Run test to verify it fails**

Run:
```bash
docker compose exec octane php artisan test --filter=AdminNavigationTest
```

Expected: FAIL - navigation links don't exist yet

**Step 3: Update navigation menu**

Modify `resources/views/navigation-menu.blade.php` - find the navigation links section and add:

```blade
<!-- Add after existing navigation links (e.g., Dashboard) -->
<x-nav-link href="{{ route('admin.partners') }}" :active="request()->routeIs('admin.partners')">
    {{ __('Partners') }}
</x-nav-link>

<x-nav-link href="{{ route('admin.revenue') }}" :active="request()->routeIs('admin.revenue')">
    {{ __('Revenue') }}
</x-nav-link>
```

**Step 4: Run tests to verify they pass**

Run:
```bash
docker compose exec octane php artisan test --filter=AdminNavigationTest
```

Expected: All tests PASS

**Step 5: Commit**

```bash
git add resources/views/navigation-menu.blade.php tests/Feature/AdminNavigationTest.php
git commit -m "feat: add navigation links for partner and revenue admin"
```

---

### Task 12: Run Full Test Suite

**Files:** None

**Step 1: Run all tests**

Run:
```bash
docker compose exec octane php artisan test
```

Expected: All tests PASS

**Step 2: Check for any failures**

If any tests fail:
- Review error messages
- Fix failing tests
- Re-run test suite until all pass

**Step 3: Reload Octane**

Run:
```bash
docker compose exec octane php artisan octane:reload
```

**Step 4: Commit if fixes were needed**

```bash
git add .
git commit -m "fix: resolve test failures"
```

---

### Task 13: Update CLAUDE.md Documentation

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Add partner system documentation**

Modify `/Users/bjorn/web/magnifiq/CLAUDE.md` - add new section after "Multi-Tenancy Model":

```markdown
### Partner (Whitelabel) System

The application supports partner (whitelabel) functionality where partners can own and manage multiple customer teams:

- **Partner Teams**: Teams with `type = 'partner'` can own other teams via `parent_team_id`
- **Revenue Tracking**: `PartnerRevenue` model tracks customer revenue and partner share percentage
- **Custom Branding**: Partners can upload logos displayed on login/registration pages via `?partner={slug}` parameter
- **Access Control**: Partner owners/members can view and manage owned customer teams via updated `TeamPolicy`

**Key Models:**
- `Team::isPartner()` - Check if team is partner type
- `Team::ownedTeams()` - Get teams owned by partner
- `PartnerRevenue` - Revenue tracking with period, amounts (in cents), and share percentage

**Admin UI:**
- `/admin/partners` - Partner CRUD, team assignment, logo management
- `/admin/revenue` - Revenue dashboard with filtering and totals

**Branding:**
- Query parameter: `/register?partner=acme` displays Acme partner logo
- Middleware: `DetectPartnerContext` injects partner into views
- Component: `authentication-card-logo` conditionally renders partner logo

**Database Schema:**
```sql
teams.type (default: 'customer', partner: 'partner')
teams.parent_team_id (FK to teams, nullable)
teams.logo_path (partner logo storage path)
teams.partner_slug (unique slug for branding URLs)

partner_revenue (tracks revenue per customer per period)
```
```

**Step 2: Commit documentation**

```bash
git add CLAUDE.md
git commit -m "docs: add partner whitelabel system documentation"
```

---

## Phase 7: Future Enhancements (Not Implemented)

### Potential Future Features

1. **Subdomain-based Partner Detection**
   - Map `acme.magnifiq.com` → `partner_slug = 'acme'`
   - Requires DNS wildcard and subdomain middleware

2. **Partner-specific Revenue Share Per Customer**
   - Allow different share percentages per customer team
   - Add `partner_share_percent` column to `teams` table

3. **Automated Revenue Calculation**
   - Job to aggregate customer billing data
   - Auto-create `PartnerRevenue` records monthly

4. **Partner Portal**
   - Dedicated dashboard for partners to view their owned teams
   - Self-service revenue reporting
   - Customer management interface

5. **Advanced Authorization**
   - Role-based permissions (partner admin, partner viewer, etc.)
   - Granular control over partner actions on customer teams

6. **Multi-currency Support**
   - Handle partners in different countries
   - Currency conversion for revenue tracking

---

## Testing Checklist

Before marking complete, verify:

- [ ] All migrations run successfully
- [ ] All unit tests pass
- [ ] All feature tests pass
- [ ] Partner CRUD works in UI
- [ ] Team assignment works in UI
- [ ] Logo upload works in UI
- [ ] Partner branding displays on login/register with `?partner=slug`
- [ ] Revenue dashboard displays correctly
- [ ] Navigation links work
- [ ] Authorization policies enforce partner access correctly
- [ ] CLAUDE.md updated with partner system docs

---

## Rollback Plan

If deployment fails:

1. **Database Rollback:**
   ```bash
   php artisan migrate:rollback --step=3
   ```
   (Rolls back 3 migrations: branding fields, revenue table, partner fields)

2. **Code Rollback:**
   ```bash
   git revert HEAD~13..HEAD
   ```
   (Reverts all 13 commits from this feature)

3. **Clear Cached Views:**
   ```bash
   php artisan view:clear
   php artisan octane:reload
   ```

---

## Notes

- All revenue amounts stored in cents to avoid floating-point precision issues
- Partner logo stored on `public` disk by default (change via config if needed)
- Partner slug must be unique across all partners
- Customer teams can only have one parent partner (no multi-parent hierarchy)
- Partner owners automatically get access to owned teams via `TeamPolicy` updates
- No billing integration yet - revenue records are manual for now
