<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminJobs extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'pending';

    #[Url(as: 'queue')]
    public string $queueFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedQueueFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Retry a failed job by UUID.
     */
    public function retryJob(string $uuid): void
    {
        $failedJob = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        if (! $failedJob) {
            session()->flash('error', 'Failed job not found.');

            return;
        }

        $this->pushJobBackToQueue($failedJob);

        DB::table('failed_jobs')->where('uuid', $uuid)->delete();

        session()->flash('message', 'Job queued for retry.');
    }

    /**
     * Delete a failed job by ID.
     */
    public function deleteFailedJob(int $id): void
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        session()->flash('message', 'Failed job deleted.');
    }

    /**
     * Flush all failed jobs.
     */
    public function flushFailedJobs(): void
    {
        DB::table('failed_jobs')->truncate();

        session()->flash('message', 'All failed jobs have been deleted.');
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailedJobs(): void
    {
        $failedJobs = DB::table('failed_jobs')->get();
        $count = 0;

        foreach ($failedJobs as $failedJob) {
            $this->pushJobBackToQueue($failedJob);
            DB::table('failed_jobs')->where('id', $failedJob->id)->delete();
            $count++;
        }

        session()->flash('message', "{$count} failed job(s) queued for retry.");
    }

    /**
     * Push a failed job back to the queue.
     */
    private function pushJobBackToQueue(object $failedJob): void
    {
        $payload = json_decode($failedJob->payload, true);

        // Reset the attempts in the payload
        $payload['attempts'] = 0;

        DB::table('jobs')->insert([
            'queue' => $failedJob->queue,
            'payload' => json_encode($payload),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin-jobs', [
            'pendingJobs' => $this->tab === 'pending' ? $this->getPendingJobs() : null,
            'failedJobs' => $this->tab === 'failed' ? $this->getFailedJobs() : null,
            'queues' => $this->getQueues(),
            'stats' => $this->getStats(),
        ]);
    }

    /**
     * Get pending jobs from the jobs table.
     */
    private function getPendingJobs()
    {
        $searchTerm = strtolower($this->search);

        return DB::table('jobs')
            ->when($this->queueFilter, fn ($query) => $query->where('queue', $this->queueFilter))
            ->when($this->search, fn ($query) => $query->whereRaw('lower(payload) like ?', ["%{$searchTerm}%"]))
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    /**
     * Get failed jobs from the failed_jobs table.
     */
    private function getFailedJobs()
    {
        $searchTerm = strtolower($this->search);

        return DB::table('failed_jobs')
            ->when($this->queueFilter, fn ($query) => $query->where('queue', $this->queueFilter))
            ->when($this->search, function ($query) use ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('lower(payload) like ?', ["%{$searchTerm}%"])
                        ->orWhereRaw('lower(exception) like ?', ["%{$searchTerm}%"]);
                });
            })
            ->orderBy('failed_at', 'desc')
            ->paginate(20);
    }

    /**
     * Get unique queue names from both tables.
     *
     * @return array<string>
     */
    private function getQueues(): array
    {
        $pendingQueues = DB::table('jobs')
            ->distinct()
            ->pluck('queue')
            ->toArray();

        $failedQueues = DB::table('failed_jobs')
            ->distinct()
            ->pluck('queue')
            ->toArray();

        return array_unique(array_merge($pendingQueues, $failedQueues));
    }

    /**
     * Get statistics for both job types.
     *
     * @return array<string, mixed>
     */
    private function getStats(): array
    {
        return [
            'pending_total' => DB::table('jobs')->count(),
            'pending_by_queue' => DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray(),
            'failed_total' => DB::table('failed_jobs')->count(),
            'failed_by_queue' => DB::table('failed_jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->toArray(),
        ];
    }

    /**
     * Parse job payload and extract the job class name.
     */
    public static function parseJobClass(string $payload): string
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Unknown';
        }

        $displayName = $data['displayName'] ?? $data['job'] ?? null;

        if (! $displayName) {
            return 'Unknown';
        }

        return class_basename($displayName);
    }

    /**
     * Parse job payload and extract command data if available.
     *
     * @return array<string, mixed>|null
     */
    public static function parseJobData(string $payload): ?array
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return [
            'displayName' => $data['displayName'] ?? null,
            'job' => $data['job'] ?? null,
            'maxTries' => $data['maxTries'] ?? null,
            'timeout' => $data['timeout'] ?? null,
            'backoff' => $data['backoff'] ?? null,
        ];
    }

    /**
     * Format a Unix timestamp for display.
     */
    public static function formatTimestamp(?int $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp($timestamp)->format('M j, Y H:i:s');
    }

    /**
     * Get time ago string from Unix timestamp.
     */
    public static function timeAgo(?int $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp($timestamp)->diffForHumans();
    }
}
