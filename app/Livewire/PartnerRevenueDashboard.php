<?php

namespace App\Livewire;

use App\Models\PartnerRevenue;
use App\Models\Team;
use Livewire\Component;
use Livewire\WithPagination;

class PartnerRevenueDashboard extends Component
{
    use WithPagination;

    public ?int $selectedPartnerId = null;

    public function render()
    {
        $partners = Team::query()
            ->where('type', 'partner')
            ->orderBy('name')
            ->get();

        $revenueQuery = PartnerRevenue::query()
            ->with(['partnerTeam', 'customerTeam'])
            ->latest('period_start');

        if ($this->selectedPartnerId) {
            $revenueQuery->where('partner_team_id', $this->selectedPartnerId);
        }

        $revenueRecords = $revenueQuery->paginate(20);

        $totalPartnerRevenue = PartnerRevenue::query()
            ->when($this->selectedPartnerId, fn($q) => $q->where('partner_team_id', $this->selectedPartnerId))
            ->sum('partner_revenue_cents');

        return view('livewire.partner-revenue-dashboard', [
            'partners' => $partners,
            'revenueRecords' => $revenueRecords,
            'totalPartnerRevenue' => $totalPartnerRevenue,
        ]);
    }
}
