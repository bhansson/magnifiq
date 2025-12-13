<?php

namespace App\Console\Commands;

use App\Jobs\SyncStoreProducts;
use App\Models\StoreConnection;
use Illuminate\Console\Command;

class SyncStoreConnectionsCommand extends Command
{
    protected $signature = 'stores:sync
                            {--connection= : Sync a specific connection by ID}
                            {--platform= : Sync all connections for a specific platform}
                            {--force : Force sync even if recently synced}';

    protected $description = 'Sync products from connected store platforms';

    public function handle(): int
    {
        $query = StoreConnection::query()
            ->where('status', StoreConnection::STATUS_CONNECTED);

        if ($connectionId = $this->option('connection')) {
            $query->where('id', $connectionId);
        }

        if ($platform = $this->option('platform')) {
            $query->where('platform', $platform);
        }

        if (! $this->option('force')) {
            $intervalMinutes = config('store-integrations.sync.interval_minutes', 15);
            $query->where(function ($q) use ($intervalMinutes) {
                $q->whereNull('last_synced_at')
                    ->orWhere('last_synced_at', '<', now()->subMinutes($intervalMinutes));
            });
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->info('No store connections need syncing.');

            return self::SUCCESS;
        }

        $this->info("Dispatching sync jobs for {$connections->count()} connection(s)...");

        $bar = $this->output->createProgressBar($connections->count());
        $bar->start();

        foreach ($connections as $connection) {
            SyncStoreProducts::dispatch($connection);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Sync jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
