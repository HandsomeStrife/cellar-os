<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Domain\Admin\Repositories\AdminRepository;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Order\Repositories\OrderRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Domain\User\Repositories\UserRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Admin')]
class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'admin' => (new AdminRepository)->getLoggedInAdmin(),
            'userCount' => (new UserRepository)->count(),
            'supplierCount' => (new SupplierRepository)->count(),
            'productCount' => (new ProductRepository)->count(),
            'orderCount' => (new OrderRepository)->countAll(),
        ]);
    }
}
