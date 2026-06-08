<?php

declare(strict_types=1);

namespace App\Livewire;

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Guide')]
class Guide extends Component
{
    public function render()
    {
        return view('livewire.guide', [
            'plans' => [Plan::Free, ...Plan::paid()],
            'features' => Feature::cases(),
        ]);
    }
}
