<?php

namespace App\Livewire\Concerns;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for providing team context in Livewire components.
 *
 * This trait standardizes how Livewire components access the current user's team,
 * reducing boilerplate code and ensuring consistent authorization behavior across
 * components that require team-scoped data access.
 *
 * @example Usage in mount():
 *     public function mount(): void
 *     {
 *         $team = $this->getTeam(); // Aborts if no team
 *         // ... use $team
 *     }
 * @example Usage when null is acceptable:
 *     $team = $this->getTeamOrNull();
 *     if ($team) {
 *         // ... use $team
 *     }
 */
trait WithTeamContext
{
    /**
     * Get the current user's team or abort with 403.
     *
     * Use this method when team access is required. It will abort with a
     * 403 response if the user is not authenticated or has no current team.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function getTeam(): Team
    {
        $team = Auth::user()?->currentTeam;
        abort_if(! $team, 403, 'Join or create a team to access this feature.');

        return $team;
    }

    /**
     * Get the current user's team or null if unavailable.
     *
     * Use this method when operations should gracefully handle missing team
     * context without aborting. Useful for optional team-scoped operations
     * or polling methods that shouldn't cause fatal errors.
     */
    protected function getTeamOrNull(): ?Team
    {
        return Auth::user()?->currentTeam;
    }
}
