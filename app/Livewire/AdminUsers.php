<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminUsers extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $role = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $searchTerm = strtolower($this->search);

        $users = User::query()
            ->when($this->search, function ($query) use ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->whereRaw('lower(name) like ?', ["%{$searchTerm}%"])
                        ->orWhereRaw('lower(email) like ?', ["%{$searchTerm}%"]);
                });
            })
            ->when($this->role, function ($query) {
                $query->where('role', $this->role);
            })
            ->withCount('teams')
            ->latest()
            ->paginate(20);

        return view('livewire.admin-users', [
            'users' => $users,
        ]);
    }
}
