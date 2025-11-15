<?php

namespace App\Livewire;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ManagePartners extends Component
{
    use WithPagination, WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50|unique:teams,partner_slug')]
    public string $partner_slug = '';

    #[Validate('nullable|numeric|min:0|max:100')]
    public ?float $partner_share_percent = 20.00;

    #[Validate('nullable|image|max:2048')]
    public $logo = null;

    public bool $showCreateModal = false;

    public function createPartner(): void
    {
        $this->validate();

        $logoPath = null;
        if ($this->logo) {
            $logoPath = $this->logo->store('partners/logos', 'public');
        }

        Team::create([
            'user_id' => Auth::id() ?? 1, // Fallback for testing environments
            'name' => $this->name,
            'type' => 'partner',
            'partner_slug' => $this->partner_slug ?: null,
            'logo_path' => $logoPath,
            'personal_team' => false,
        ]);

        $this->reset(['name', 'partner_slug', 'partner_share_percent', 'logo', 'showCreateModal']);
        $this->resetPage();

        session()->flash('message', 'Partner created successfully.');
    }

    public function deletePartner(int $partnerId): void
    {
        $partner = Team::query()
            ->where('type', 'partner')
            ->findOrFail($partnerId);

        $partner->delete();

        session()->flash('message', 'Partner deleted successfully.');
    }

    public function render()
    {
        $partners = Team::query()
            ->where('type', 'partner')
            ->withCount('ownedTeams')
            ->latest()
            ->paginate(15);

        return view('livewire.manage-partners', [
            'partners' => $partners,
        ]);
    }
}
