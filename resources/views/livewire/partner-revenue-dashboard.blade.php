<div>
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-white">Partner Revenue</h2>

        <div>
            <select wire:model.live="selectedPartnerId" class="border-gray-300 dark:border-zinc-700 bg-white dark:bg-zinc-800/50 text-gray-900 dark:text-zinc-100 rounded-xl shadow-sm focus:border-amber-500 dark:focus:border-amber-500 focus:ring-amber-500/20">
                <option value="">All Partners</option>
                @foreach ($partners as $partner)
                    <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-6 bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 rounded-xl p-6">
        <div class="text-sm text-gray-600 dark:text-zinc-400">Total Partner Revenue</div>
        <div class="text-3xl font-bold text-gray-900 dark:text-white">
            ${{ number_format($totalPartnerRevenue / 100, 2) }}
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900/50 shadow dark:shadow-none dark:ring-1 dark:ring-zinc-800 overflow-hidden sm:rounded-xl">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-800">
            <thead class="bg-gray-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Partner</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Customer Revenue</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Share %</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-zinc-400 uppercase tracking-wider">Partner Revenue</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900/50 divide-y divide-gray-200 dark:divide-zinc-800">
                @forelse ($revenueRecords as $record)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            {{ $record->partnerTeam->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $record->customerTeam->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400">
                            {{ $record->period_start->format('Y-m-d') }} — {{ $record->period_end->format('Y-m-d') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white text-right">
                            ${{ number_format($record->customer_revenue_cents / 100, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-zinc-400 text-right">
                            {{ $record->partner_share_percent }}%
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-white text-right">
                            ${{ number_format($record->partner_revenue_cents / 100, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-zinc-400">
                            No revenue records found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $revenueRecords->links() }}
    </div>
</div>
