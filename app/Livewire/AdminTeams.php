<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminTeams extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $type = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $searchTerm = strtolower($this->search);

        $teams = Team::query()
            ->when($this->search, function ($query) use ($searchTerm) {
                $query->whereRaw('lower(name) like ?', ["%{$searchTerm}%"]);
            })
            ->when($this->type, function ($query) {
                $query->where('type', $this->type);
            })
            ->with('owner:id,name,email')
            ->withCount(['users', 'productFeeds'])
            ->latest()
            ->paginate(20);

        return view('livewire.admin-teams', [
            'teams' => $teams,
        ]);
    }
}
