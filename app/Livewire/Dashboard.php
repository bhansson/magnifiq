<?php

namespace App\Livewire;

use App\Models\ProductAiJob;
use App\Models\TeamActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
class Dashboard extends Component
{
    public int $recentJobsLimit = 10;

    public int $activityLimit = 100;

    public function render(): View
    {
        $team = Auth::user()->currentTeam;

        $recentJobs = ProductAiJob::query()
            ->with([
                'product:id,title,sku',
                'template:id,name,slug',
                'photoStudioGeneration:id,product_ai_job_id,storage_path,storage_disk',
            ])
            ->where('team_id', $team->id)
            ->orderByDesc('queued_at')
            ->orderByDesc('id')
            ->limit($this->recentJobsLimit)
            ->get();

        $jobStats = [
            'queued' => ProductAiJob::where('team_id', $team->id)
                ->where('status', ProductAiJob::STATUS_QUEUED)
                ->count(),
            'processing' => ProductAiJob::where('team_id', $team->id)
                ->where('status', ProductAiJob::STATUS_PROCESSING)
                ->count(),
            'completed_today' => ProductAiJob::where('team_id', $team->id)
                ->where('status', ProductAiJob::STATUS_COMPLETED)
                ->whereDate('finished_at', today())
                ->count(),
            'failed_today' => ProductAiJob::where('team_id', $team->id)
                ->where('status', ProductAiJob::STATUS_FAILED)
                ->whereDate('finished_at', today())
                ->count(),
        ];

        $activities = TeamActivity::query()
            ->with(['user:id,name,profile_photo_path'])
            ->where('team_id', $team->id)
            ->whereNotIn('type', [
                TeamActivity::TYPE_JOB_QUEUED,
                TeamActivity::TYPE_JOB_FAILED,
            ])
            ->orderByDesc('created_at')
            ->limit($this->activityLimit)
            ->get();

        return view('livewire.dashboard', [
            'recentJobs' => $recentJobs,
            'jobStats' => $jobStats,
            'activities' => $activities,
        ]);
    }
}
