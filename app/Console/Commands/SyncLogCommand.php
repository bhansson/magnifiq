<?php

namespace App\Console\Commands;

use App\Models\StoreSyncJob;
use Illuminate\Console\Command;

class SyncLogCommand extends Command
{
    protected $signature = 'sync:log
                            {--limit=20 : Number of entries to show}
                            {--failed : Show only failed syncs}
                            {--connection= : Filter by connection ID}';

    protected $description = 'Show recent store sync job logs';

    public function handle(): int
    {
        $query = StoreSyncJob::query()
            ->with('storeConnection')
            ->latest();

        if ($this->option('failed')) {
            $query->where('status', StoreSyncJob::STATUS_FAILED);
        }

        if ($connectionId = $this->option('connection')) {
            $query->where('store_connection_id', $connectionId);
        }

        $jobs = $query->limit((int) $this->option('limit'))->get();

        if ($jobs->isEmpty()) {
            $this->info('No sync jobs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Store', 'Status', 'Synced', 'Created', 'Updated', 'Deleted', 'Duration', 'Time', 'Error'],
            $jobs->map(fn (StoreSyncJob $job) => [
                $job->id,
                $job->storeConnection?->store_identifier ?? 'N/A',
                $this->formatStatus($job->status),
                $job->products_synced ?? '-',
                $job->products_created ?? '-',
                $job->products_updated ?? '-',
                $job->products_deleted ?? '-',
                $job->duration ?? '-',
                $job->created_at->format('M d H:i'),
                $job->error_message ? substr($job->error_message, 0, 40).'...' : '-',
            ])
        );

        // Show full error for failed jobs
        $failedJobs = $jobs->where('status', StoreSyncJob::STATUS_FAILED);
        if ($failedJobs->isNotEmpty()) {
            $this->newLine();
            $this->error('Failed sync details:');
            foreach ($failedJobs as $job) {
                $this->line("  [{$job->id}] {$job->created_at}: {$job->error_message}");
            }
        }

        return self::SUCCESS;
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            StoreSyncJob::STATUS_COMPLETED => '<fg=green>✓ completed</>',
            StoreSyncJob::STATUS_FAILED => '<fg=red>✗ failed</>',
            StoreSyncJob::STATUS_PROCESSING => '<fg=yellow>⟳ processing</>',
            default => $status,
        };
    }
}
