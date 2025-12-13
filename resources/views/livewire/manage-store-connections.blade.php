<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-xl bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 px-4 py-3 text-sm text-green-800 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 px-4 py-3 text-sm text-red-800 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Store Connections</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-zinc-300">
                Connect your e-commerce stores to automatically sync products.
            </p>
        </div>
        <x-button type="button" wire:click="openConnectModal" title="Connect new store">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Store
        </x-button>
    </div>

    <div class="space-y-4">
        @forelse ($connections as $connection)
            @php
                $lastSync = $connection->syncJobs->first();
                $statusColors = [
                    'pending' => 'bg-yellow-100 dark:bg-yellow-500/20 text-yellow-800 dark:text-yellow-400',
                    'connected' => 'bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400',
                    'syncing' => 'bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400',
                    'error' => 'bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400',
                ];
                $statusColor = $statusColors[$connection->status] ?? $statusColors['pending'];
            @endphp
            <div class="rounded-xl border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/50 shadow-sm dark:shadow-none">
                <div class="px-5 py-4 sm:flex sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-2">
                                <svg class="h-6 w-6 text-[#95BF47]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M15.337 3.415c-.193-.016-.393-.024-.598-.024-.205 0-.405.008-.598.024-.5.038-.89.118-1.17.238-.28.12-.52.28-.72.48-.2.2-.36.44-.48.72-.12.28-.2.67-.238 1.17-.016.193-.024.393-.024.598 0 .205.008.405.024.598.038.5.118.89.238 1.17.12.28.28.52.48.72.2.2.44.36.72.48.28.12.67.2 1.17.238.193.016.393.024.598.024.205 0 .405-.008.598-.024.5-.038.89-.118 1.17-.238.28-.12.52-.28.72-.48.2-.2.36-.44.48-.72.12-.28.2-.67.238-1.17.016-.193.024-.393.024-.598 0-.205-.008-.405-.024-.598-.038-.5-.118-.89-.238-1.17-.12-.28-.28-.52-.48-.72-.2-.2-.44-.36-.72-.48-.28-.12-.67-.2-1.17-.238zm-4.588 4.67l-2.18 8.95c-.104.426.066.875.425 1.125l6.256 4.377c.384.269.896.25 1.26-.047l5.5-4.5c.33-.27.477-.707.377-1.115l-2.5-10.25c-.077-.316-.287-.582-.577-.73l-5-2.5c-.187-.094-.39-.142-.594-.142-.33 0-.655.127-.897.38l-1.253 1.303c-.235.244-.55.38-.882.38h-.936c-.552 0-1 .448-1 1v1c0 .265.105.52.293.707l.707.707v.355z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $connection->name }}</h3>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColor }}">
                                {{ ucfirst($connection->status) }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-zinc-300">{{ $connection->store_identifier }}</p>

                        <dl class="grid gap-1 text-sm text-gray-600 dark:text-zinc-300 sm:grid-cols-3">
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Platform</dt>
                                <dd>{{ ucfirst($connection->platform) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Products</dt>
                                <dd>{{ $connection->productFeed?->products()->count() ?? 0 }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-zinc-400">Last synced</dt>
                                <dd>{{ $connection->last_synced_at?->diffForHumans() ?? 'Never' }}</dd>
                            </div>
                        </dl>

                        @if ($connection->last_error)
                            <p class="text-sm text-red-600 dark:text-red-400">
                                {{ $connection->getFriendlyError() }}
                            </p>
                        @endif
                    </div>

                    <div class="mt-4 flex items-center gap-1 sm:mt-0">
                        @if ($connection->isConnected() || ($connection->status === 'error' && $connection->access_token))
                            {{-- Sync Button (only if connected or error with valid token) --}}
                            <button
                                type="button"
                                wire:click="sync({{ $connection->id }})"
                                wire:loading.attr="disabled"
                                wire:target="sync({{ $connection->id }})"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 focus:outline-none transition-all disabled:opacity-50"
                                title="Sync products now">
                                <svg class="w-4 h-4" wire:loading.remove wire:target="sync({{ $connection->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <svg class="w-4 h-4 animate-spin" wire:loading wire:target="sync({{ $connection->id }})" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif

                        {{-- Test Connection Button --}}
                        <button
                            type="button"
                            wire:click="testConnection({{ $connection->id }})"
                            wire:loading.attr="disabled"
                            wire:target="testConnection({{ $connection->id }})"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 focus:outline-none transition-all disabled:opacity-50"
                            title="Test connection">
                            <svg class="w-4 h-4" wire:loading.remove wire:target="testConnection({{ $connection->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <svg class="w-4 h-4 animate-spin" wire:loading wire:target="testConnection({{ $connection->id }})" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>

                        {{-- Disconnect Button --}}
                        <button
                            type="button"
                            x-on:click.prevent="if (window.confirm('Disconnect from {{ addslashes($connection->name) }}? This will remove the store connection but keep imported products.')) { $wire.disconnect({{ $connection->id }}) }"
                            wire:loading.attr="disabled"
                            wire:target="disconnect({{ $connection->id }})"
                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-gray-400 dark:text-zinc-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 focus:outline-none transition-all disabled:opacity-50"
                            title="Disconnect store">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                @if ($lastSync)
                    <div class="border-t border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-800/30 px-5 py-3 text-xs text-gray-500 dark:text-zinc-400">
                        Last sync: {{ $lastSync->products_synced ?? 0 }} products
                        ({{ $lastSync->products_created ?? 0 }} new, {{ $lastSync->products_updated ?? 0 }} updated, {{ $lastSync->products_deleted ?? 0 }} removed)
                        &bull; {{ $lastSync->created_at->diffForHumans() }}
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/30 px-5 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No store connections</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                    Connect your e-commerce store to automatically import and sync products.
                </p>
                <div class="mt-6">
                    <x-button type="button" wire:click="openConnectModal">
                        Connect your first store
                    </x-button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Connect Modal --}}
    <x-dialog-modal wire:model.live="showConnectModal">
        <x-slot name="title">
            Connect a Store
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">
                <div>
                    <x-label for="selectedPlatform" value="Platform" />
                    <select wire:model.live="selectedPlatform" id="selectedPlatform"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($availablePlatforms as $key => $platform)
                            <option value="{{ $key }}">{{ $platform['name'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error for="selectedPlatform" class="mt-2" />
                </div>

                <div>
                    <x-label for="storeIdentifier" value="Store URL" />
                    <x-input wire:model="storeIdentifier" id="storeIdentifier" type="text" class="mt-1 block w-full"
                             placeholder="{{ $availablePlatforms[$selectedPlatform]['placeholder'] ?? '' }}" />
                    <p class="mt-1 text-sm text-gray-500 dark:text-zinc-400">
                        {{ $availablePlatforms[$selectedPlatform]['help'] ?? '' }}
                    </p>
                    <x-input-error for="storeIdentifier" class="mt-2" />
                </div>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="closeConnectModal">
                Cancel
            </x-secondary-button>

            <x-button wire:click="connect" class="ml-3">
                Connect to {{ $availablePlatforms[$selectedPlatform]['name'] ?? 'Store' }}
            </x-button>
        </x-slot>
    </x-dialog-modal>
</div>
