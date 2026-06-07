<?php

declare(strict_types=1);

namespace App\Livewire;

use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Order\Repositories\OrderRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\User\Repositories\UserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user = (new UserRepository)->getLoggedInUser();

        return view('livewire.dashboard', [
            'user' => $user,
            'plan' => $user?->plan,
            'productCount' => (new ProductRepository)->count(),
            'supplierCount' => (new SupplierRepository)->count(),
            'openOrderCount' => (new OrderRepository)->countOpen(),
        ]);
    }
}
