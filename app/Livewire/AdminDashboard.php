<?php

namespace App\Livewire;

use App\Models\PhotoStudioGeneration;
use App\Models\ProductAiJob;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Component;

class AdminDashboard extends Component
{
    use Concerns\AuthorizesSuperAdmin;

    /** @var array<string, mixed> */
    public array $photoStudioStats = [];

    public function mount(): void
    {
        $this->photoStudioStats = $this->getPhotoStudioStats();
    }

    public function refreshPhotoStudio(): void
    {
        $this->photoStudioStats = $this->getPhotoStudioStats();
    }

    public function render()
    {
        return view('livewire.admin-dashboard', [
            'envConfig' => $this->getEnvConfig(),
            'userStats' => $this->getUserStats(),
            'teamStats' => $this->getTeamStats(),
            'jobStats' => $this->getJobStats(),
        ]);
    }

    /**
     * Get environment and AI model configuration.
     *
     * @return array<string, mixed>
     */
    private function getEnvConfig(): array
    {
        return [
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'chat_driver' => config('ai.features.chat.driver'),
            'chat_model' => config('ai.features.chat.model'),
            'vision_driver' => config('ai.features.vision.driver'),
            'vision_model' => config('ai.features.vision.model'),
            'default_image_model' => config('photo-studio.default_image_model'),
        ];
    }

    /**
     * Get user statistics.
     *
     * @return array<string, mixed>
     */
    private function getUserStats(): array
    {
        $weekAgo = Carbon::now()->subWeek();
        $monthAgo = Carbon::now()->subMonth();

        return [
            'total' => User::count(),
            'by_role' => [
                'superadmin' => User::where('role', 'superadmin')->count(),
                'admin' => User::where('role', 'admin')->count(),
                'user' => User::where('role', 'user')->count(),
            ],
            'created_this_week' => User::where('created_at', '>=', $weekAgo)->count(),
            'created_this_month' => User::where('created_at', '>=', $monthAgo)->count(),
            'recent' => User::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'email', 'role', 'created_at']),
        ];
    }

    /**
     * Get team statistics.
     *
     * @return array<string, mixed>
     */
    private function getTeamStats(): array
    {
        $weekAgo = Carbon::now()->subWeek();

        return [
            'total' => Team::count(),
            'by_type' => [
                'customer' => Team::where('type', 'customer')->count(),
                'partner' => Team::where('type', 'partner')->count(),
            ],
            'with_product_feeds' => Team::whereHas('productFeeds')->count(),
            'created_this_week' => Team::where('created_at', '>=', $weekAgo)->count(),
            'recent' => Team::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'type', 'created_at']),
        ];
    }

    /**
     * Get AI job statistics.
     *
     * @return array<string, mixed>
     */
    private function getJobStats(): array
    {
        $today = Carbon::today();

        return [
            'total' => ProductAiJob::count(),
            'by_status' => [
                'queued' => ProductAiJob::where('status', ProductAiJob::STATUS_QUEUED)->count(),
                'processing' => ProductAiJob::where('status', ProductAiJob::STATUS_PROCESSING)->count(),
                'completed' => ProductAiJob::where('status', ProductAiJob::STATUS_COMPLETED)->count(),
                'failed' => ProductAiJob::where('status', ProductAiJob::STATUS_FAILED)->count(),
            ],
            'created_today' => ProductAiJob::whereDate('created_at', $today)->count(),
            'recent_failed' => ProductAiJob::query()
                ->where('status', ProductAiJob::STATUS_FAILED)
                ->latest()
                ->limit(5)
                ->get(['id', 'job_type', 'last_error', 'created_at']),
        ];
    }

    /**
     * Get Photo Studio generation statistics.
     *
     * @return array<string, mixed>
     */
    private function getPhotoStudioStats(): array
    {
        $weekAgo = Carbon::now()->subWeek();
        $monthAgo = Carbon::now()->subMonth();

        return [
            'total' => PhotoStudioGeneration::count(),
            'created_this_week' => PhotoStudioGeneration::where('created_at', '>=', $weekAgo)->count(),
            'created_this_month' => PhotoStudioGeneration::where('created_at', '>=', $monthAgo)->count(),
            'recent' => PhotoStudioGeneration::query()
                ->with('team:id,name')
                ->latest()
                ->limit(5)
                ->get(['id', 'team_id', 'model', 'storage_disk', 'storage_path', 'created_at']),
        ];
    }
}
