<?php

namespace App\Livewire;

use App\Models\ProductAiJob;
use App\Models\User;
use Livewire\Component;

class AdminUserDetail extends Component
{
    use Concerns\AuthorizesSuperAdmin;

    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user->load(['teams', 'ownedTeams']);
    }

    public function render()
    {
        $jobStats = ProductAiJob::where('user_id', $this->user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('livewire.admin-user-detail', [
            'jobStats' => $jobStats,
            'totalJobs' => array_sum($jobStats),
        ]);
    }
}
