<?php

namespace App\Livewire;

use App\Jobs\SyncStoreProducts;
use App\Models\StoreConnection;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ManageStoreConnections extends Component
{
    use Concerns\WithTeamContext;

    public bool $showConnectModal = false;

    #[Validate('required|string|in:shopify')]
    public string $selectedPlatform = 'shopify';

    #[Validate('required|string|max:255')]
    public string $storeIdentifier = '';

    public function render()
    {
        $team = $this->getTeam();

        $connections = StoreConnection::query()
            ->where('team_id', $team->id)
            ->with(['productFeed', 'syncJobs' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('name')
            ->get();

        return view('livewire.manage-store-connections', [
            'connections' => $connections,
            'team' => $team,
            'availablePlatforms' => $this->getAvailablePlatforms(),
        ]);
    }

    public function openConnectModal(): void
    {
        $this->resetValidation();
        $this->storeIdentifier = '';
        $this->selectedPlatform = 'shopify';
        $this->showConnectModal = true;
    }

    public function closeConnectModal(): void
    {
        $this->showConnectModal = false;
    }

    public function connect(): void
    {
        $this->validate();

        $team = $this->getTeam();

        $existing = StoreConnection::query()
            ->where('team_id', $team->id)
            ->where('platform', $this->selectedPlatform)
            ->where('store_identifier', $this->normalizeStoreIdentifier($this->storeIdentifier))
            ->exists();

        if ($existing) {
            $this->addError('storeIdentifier', 'This store is already connected.');

            return;
        }

        $this->redirect(
            route('store.oauth.redirect', [
                'platform' => $this->selectedPlatform,
                'store' => $this->storeIdentifier,
            ])
        );
    }

    public function sync(int $connectionId): void
    {
        $team = $this->getTeam();

        $connection = StoreConnection::query()
            ->where('id', $connectionId)
            ->where('team_id', $team->id)
            ->whereIn('status', [StoreConnection::STATUS_CONNECTED, StoreConnection::STATUS_ERROR])
            ->first();

        if (! $connection) {
            session()->flash('error', 'Connection not found or not in a syncable state.');

            return;
        }

        // Reset error status before retrying
        if ($connection->status === StoreConnection::STATUS_ERROR) {
            $connection->update(['status' => StoreConnection::STATUS_CONNECTED, 'last_error' => null]);
        }

        SyncStoreProducts::dispatch($connection);

        session()->flash('success', "Sync job dispatched for {$connection->name}.");
    }

    public function testConnection(int $connectionId): void
    {
        $team = $this->getTeam();

        $connection = StoreConnection::query()
            ->where('id', $connectionId)
            ->where('team_id', $team->id)
            ->first();

        if (! $connection) {
            session()->flash('error', 'Connection not found.');

            return;
        }

        $adapter = \App\Facades\Store::forPlatform($connection->platform);
        $isValid = $adapter->testConnection($connection);

        if ($isValid) {
            session()->flash('success', "Connection to {$connection->name} is working.");
        } else {
            $connection->markError('Connection test failed');
            session()->flash('error', "Connection to {$connection->name} failed. Please reconnect.");
        }
    }

    public function disconnect(int $connectionId): void
    {
        $team = $this->getTeam();

        $connection = StoreConnection::query()
            ->where('id', $connectionId)
            ->where('team_id', $team->id)
            ->first();

        if (! $connection) {
            session()->flash('error', 'Connection not found.');

            return;
        }

        $name = $connection->name;
        $connection->delete();

        session()->flash('success', "Disconnected from {$name}.");
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function getAvailablePlatforms(): array
    {
        return [
            'shopify' => [
                'name' => 'Shopify',
                'icon' => 'shopify',
                'placeholder' => 'your-store.myshopify.com',
                'help' => 'Enter your Shopify store URL (e.g., your-store.myshopify.com)',
            ],
        ];
    }

    protected function normalizeStoreIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/^https?:\/\//', '', $identifier);
        $identifier = rtrim($identifier, '/');

        if (! str_ends_with($identifier, '.myshopify.com')) {
            $identifier .= '.myshopify.com';
        }

        return $identifier;
    }
}
