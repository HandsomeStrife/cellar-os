<?php

declare(strict_types=1);

namespace App\Livewire\Map;

use Domain\Catalogue\Repositories\ProductRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Sourcing map')]
class Index extends Component
{
    public function render()
    {
        $products = (new ProductRepository)->allForMap();

        // Values are rendered into Leaflet popups via textContent (see the view),
        // and serialised through @js (JSON-encoded), so no markup stripping is
        // needed — and legitimate names containing "<" survive intact.
        $points = $products->map(fn ($p) => [
            'lat' => (float) $p->latitude,
            'lng' => (float) $p->longitude,
            'name' => (string) $p->wine_name,
            'producer' => $p->producer,
            'country' => $p->country,
            'colour' => $p->colour?->getSwatch() ?? '#7b1e3b',
        ])->values()->all();

        $countries = $products
            ->groupBy(fn ($p) => $p->country ?: 'Unknown')
            ->map->count()
            ->sortDesc();

        return view('livewire.map.index', [
            'points' => $points,
            'countries' => $countries,
            'total' => $products->count(),
        ]);
    }
}
