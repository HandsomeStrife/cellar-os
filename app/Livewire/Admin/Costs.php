<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Carbon\CarbonImmutable;
use Domain\Supplier\Repositories\LlmCallRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('AI costs')]
class Costs extends Component
{
    use WithPagination;

    public function render()
    {
        $repo = new LlmCallRepository;

        return view('livewire.admin.costs', [
            'allTime' => $repo->totals(),
            'last30' => $repo->totals(CarbonImmutable::now()->subDays(30)),
            'last7' => $repo->totals(CarbonImmutable::now()->subDays(7)),
            'byModel' => $repo->byModel(),
            'calls' => $repo->paginate(),
        ]);
    }
}
