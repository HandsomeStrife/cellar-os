<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Carbon\CarbonImmutable;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Enquiry\Repositories\EnquiryRepository;
use Domain\Order\Repositories\OrderRepository;
use Domain\Supplier\Repositories\LlmCallRepository;
use Domain\Supplier\Repositories\SupplierDocumentRepository;
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
            // Platform figures
            'userCount' => (new UserRepository)->count(),
            'supplierCount' => (new SupplierRepository)->count(),
            'productCount' => (new ProductRepository)->count(),
            'orderCount' => (new OrderRepository)->countAll(),
            // What actually needs an admin today
            'newEnquiries' => (new EnquiryRepository)->newCount(),
            'awaitingAnalysis' => (new SupplierDocumentRepository)->countAwaitingAnalysis(),
            'aiWeek' => (new LlmCallRepository)->totals(CarbonImmutable::now()->subDays(7)),
        ]);
    }
}
