<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Company\Repositories\CompanyRepository;
use Domain\User\Repositories\UserRepository;
use Domain\Venue\Repositories\VenueRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Companies')]
class Companies extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $companies = (new CompanyRepository)->paginate($this->search);

        $users = new UserRepository;
        $venues = new VenueRepository;
        $counts = collect($companies->items())->mapWithKeys(fn ($c) => [
            $c->id => [
                'users' => $users->forCompany($c->id)->count(),
                'venues' => $venues->countForCompany($c->id),
            ],
        ])->all();

        return view('livewire.admin.companies', [
            'companies' => $companies,
            'counts' => $counts,
        ]);
    }
}
