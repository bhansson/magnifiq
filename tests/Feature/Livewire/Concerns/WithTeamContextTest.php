<?php

use App\Models\User;
use Livewire\Component;
use Livewire\Livewire;

// Test helper component that uses the trait
class TeamContextTestComponent extends Component
{
    use App\Livewire\Concerns\WithTeamContext;

    public ?int $teamId = null;

    public ?string $teamName = null;

    public bool $teamFound = false;

    public function mount(): void
    {
        $team = $this->getTeam();
        $this->teamId = $team->id;
        $this->teamName = $team->name;
        $this->teamFound = true;
    }

    public function tryGetTeam(): void
    {
        $team = $this->getTeam();
        $this->teamId = $team->id;
    }

    public function tryGetTeamOrNull(): void
    {
        $team = $this->getTeamOrNull();
        $this->teamId = $team?->id;
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            <span>Team ID: {{ $teamId }}</span>
            <span>Team Name: {{ $teamName }}</span>
        </div>
        HTML;
    }
}

test('getTeam returns the current team for authenticated user with team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test(TeamContextTestComponent::class)
        ->assertSet('teamId', $team->id)
        ->assertSet('teamName', $team->name)
        ->assertSet('teamFound', true);
});

test('getTeam aborts with 403 when user has no team', function () {
    $user = User::factory()->create();

    // User without a team
    $this->actingAs($user);

    // The component mount should abort with 403
    Livewire::test(TeamContextTestComponent::class)
        ->assertStatus(403);
});

test('getTeam aborts with 403 for unauthenticated user', function () {
    // No authenticated user
    Livewire::test(TeamContextTestComponent::class)
        ->assertStatus(403);
});

test('getTeamOrNull returns team when available', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test(TeamContextTestComponent::class)
        ->call('tryGetTeamOrNull')
        ->assertSet('teamId', $team->id);
});

test('getTeamOrNull returns null when no team', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Create a modified test where mount doesn't fail
    $component = new class extends Component
    {
        use App\Livewire\Concerns\WithTeamContext;

        public ?int $teamId = 999; // Start with non-null to verify it becomes null

        public function mount(): void
        {
            // Don't call getTeam() which would abort
        }

        public function tryGetTeamOrNull(): void
        {
            $team = $this->getTeamOrNull();
            $this->teamId = $team?->id;
        }

        public function render()
        {
            return '<div>Team ID: {{ $teamId }}</div>';
        }
    };

    Livewire::test($component::class)
        ->call('tryGetTeamOrNull')
        ->assertSet('teamId', null);
});

test('getTeamOrNull returns null for unauthenticated user', function () {
    // No authenticated user
    $component = new class extends Component
    {
        use App\Livewire\Concerns\WithTeamContext;

        public ?int $teamId = 999; // Start with non-null

        public function mount(): void
        {
            // Don't call getTeam()
        }

        public function tryGetTeamOrNull(): void
        {
            $team = $this->getTeamOrNull();
            $this->teamId = $team?->id;
        }

        public function render()
        {
            return '<div></div>';
        }
    };

    Livewire::test($component::class)
        ->call('tryGetTeamOrNull')
        ->assertSet('teamId', null);
});
