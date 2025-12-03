<?php

namespace App\Livewire;

use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\Team;
use Livewire\Component;

class AdminTeamDetail extends Component
{
    public Team $team;

    public function mount(Team $team): void
    {
        $this->team = $team->load(['owner', 'users', 'productFeeds', 'parentTeam', 'ownedTeams']);
    }

    public function render()
    {
        $jobStats = ProductAiJob::where('team_id', $this->team->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $photoStudioCount = PhotoStudioGeneration::where('team_id', $this->team->id)->count();

        return view('livewire.admin-team-detail', [
            'jobStats' => $jobStats,
            'totalJobs' => array_sum($jobStats),
            'photoStudioCount' => $photoStudioCount,
        ]);
    }
}
