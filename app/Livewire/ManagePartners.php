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
    use Concerns\AuthorizesSuperAdmin, WithFileUploads, WithPagination;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50')]
    public string $partner_slug = '';

    #[Validate('nullable|numeric|min:0|max:100')]
    public ?float $partner_share_percent = 20.00;

    #[Validate('nullable|image|max:2048')]
    public $logo = null;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public ?int $editingPartnerId = null;

    public ?string $existingLogoPath = null;

    public function createPartner(): void
    {
        $this->authorizeSuperAdmin();

        $this->validate([
            'name' => 'required|string|max:255',
            'partner_slug' => 'nullable|string|max:50|unique:teams,partner_slug',
            'partner_share_percent' => 'nullable|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:2048',
        ]);

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
        $this->authorizeSuperAdmin();

        $partner = Team::query()
            ->where('type', 'partner')
            ->findOrFail($partnerId);

        // Delete logo if exists
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }

        $partner->delete();

        session()->flash('message', 'Partner deleted successfully.');
    }

    public function openEditModal(int $partnerId): void
    {
        $this->authorizeSuperAdmin();

        $partner = Team::query()
            ->where('type', 'partner')
            ->findOrFail($partnerId);

        $this->editingPartnerId = $partner->id;
        $this->name = $partner->name;
        $this->partner_slug = $partner->partner_slug ?? '';
        $this->existingLogoPath = $partner->logo_path;
        $this->logo = null;
        $this->showEditModal = true;
    }

    public function updatePartner(): void
    {
        $this->authorizeSuperAdmin();

        $partner = Team::query()
            ->where('type', 'partner')
            ->findOrFail($this->editingPartnerId);

        $this->validate([
            'name' => 'required|string|max:255',
            'partner_slug' => 'nullable|string|max:50|unique:teams,partner_slug,'.$this->editingPartnerId,
            'partner_share_percent' => 'nullable|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:2048',
        ]);

        $logoPath = $partner->logo_path;

        // Handle logo upload
        if ($this->logo) {
            // Delete old logo if exists
            if ($partner->logo_path) {
                Storage::disk('public')->delete($partner->logo_path);
            }
            $logoPath = $this->logo->store('partners/logos', 'public');
        }

        $partner->update([
            'name' => $this->name,
            'partner_slug' => $this->partner_slug ?: null,
            'logo_path' => $logoPath,
        ]);

        $this->reset(['name', 'partner_slug', 'partner_share_percent', 'logo', 'showEditModal', 'editingPartnerId', 'existingLogoPath']);

        session()->flash('message', 'Partner updated successfully.');
    }

    public function removeLogo(): void
    {
        $this->authorizeSuperAdmin();

        if ($this->editingPartnerId) {
            $partner = Team::query()
                ->where('type', 'partner')
                ->findOrFail($this->editingPartnerId);

            if ($partner->logo_path) {
                Storage::disk('public')->delete($partner->logo_path);
                $partner->update(['logo_path' => null]);
                $this->existingLogoPath = null;
                session()->flash('message', 'Logo removed successfully.');
            }
        }
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
