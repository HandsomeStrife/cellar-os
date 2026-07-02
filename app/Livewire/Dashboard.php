<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\WithTenant;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Repositories\ProductRepository;
use Domain\Inventory\Repositories\InventoryItemRepository;
use Domain\Order\Repositories\OrderRepository;
use Domain\Supplier\Repositories\SupplierRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use WithTenant;

    private const LOW_STOCK_THRESHOLD = 12;

    public function render()
    {
        $user = $this->currentUser();
        $companyId = $user?->company_id ?? 0;

        $productRepo = new ProductRepository;
        $supplierRepo = new SupplierRepository;
        $orderRepo = new OrderRepository;

        $venues = $this->accessibleVenues();
        $items = (new InventoryItemRepository)->forVenues($venues->pluck('id')->all());

        $products = $productRepo->findMany(
            $items->pluck('product_id')->filter()->unique()->values()->all()
        );

        $bottles = 0;
        $value = 0.0;
        $labels = 0;
        $low = 0;
        $out = 0;
        $byColour = [];
        $byCountry = [];
        $byRegion = [];
        $lowStock = [];

        foreach ($items as $item) {
            $qty = $item->quantity_units;
            $price = (float) ($item->last_purchase_price ?? 0);
            $product = $products[$item->product_id] ?? null;

            $bottles += $qty;
            $value += $qty * $price;

            if ($qty === 0) {
                $out++;
            } else {
                $labels++;
                if ($qty < self::LOW_STOCK_THRESHOLD) {
                    $low++;
                    $lowStock[] = [
                        'name' => $product?->wine_name ?? 'Unknown',
                        'producer' => $product?->producer,
                        'qty' => $qty,
                        'price' => $item->last_purchase_price,
                    ];
                }

                $colour = $product?->colour?->getLabel() ?? 'Unknown';
                $country = $product?->country ?: 'Unknown';
                $region = $product?->region ?: 'Unknown';

                $byColour[$colour] = ($byColour[$colour] ?? 0) + $qty;
                $byRegion[$region] = ($byRegion[$region] ?? 0) + $qty;
                $byCountry[$country] ??= ['count' => 0, 'value' => 0.0];
                $byCountry[$country]['count'] += $qty;
                $byCountry[$country]['value'] += $qty * $price;
            }
        }

        arsort($byColour);
        arsort($byRegion);
        uasort($byCountry, fn ($a, $b) => $b['count'] <=> $a['count']);
        usort($lowStock, fn ($a, $b) => $a['qty'] <=> $b['qty']);

        // "Unknown" isn't a place — keep it out of the provenance rankings.
        unset($byRegion['Unknown'], $byCountry['Unknown']);

        // It IS part of the cellar though; just render it last in the composition.
        if (array_key_exists('Unknown', $byColour)) {
            $unknownColour = $byColour['Unknown'];
            unset($byColour['Unknown']);
            $byColour['Unknown'] = $unknownColour;
        }

        // Recent orders with supplier names (compose across contexts).
        $supplierNames = $supplierRepo->all()->mapWithKeys(fn ($s) => [$s->id => $s->name]);
        $recentOrders = $orderRepo->recent($companyId, 5)->map(fn ($o) => [
            'uuid' => $o->uuid,
            'id' => $o->id,
            'supplier' => $supplierNames[$o->supplier_id] ?? '—',
            'items' => count($o->items),
            'total' => $o->total,
            'status' => $o->status,
            'created_at' => $o->created_at,
        ]);

        return view('livewire.dashboard', [
            'user' => $user,
            'plan' => $this->companyPlan(),
            'hasVenue' => $venues->isNotEmpty(),
            'currency' => $venues->first()?->base_currency ?? 'GBP',
            // headline — "suppliers" on MY cellar dashboard means the ones I'm
            // connected to, not the whole directory.
            'productCount' => $productRepo->count(),
            'activeSuppliers' => $supplierRepo->connectedToCompany($companyId)->count(),
            'supplierCount' => $supplierRepo->count(),
            'inventoryBottles' => $bottles,
            'inventoryValue' => $value,
            'inventoryLabels' => $labels,
            // orders
            'orderCount' => $orderRepo->count($companyId),
            'openOrderCount' => $orderRepo->countOpen($companyId),
            'lowStockCount' => $low,
            'outOfStockCount' => $out,
            // breakdowns
            'byColour' => $byColour,
            'compositionTotal' => array_sum($byColour),
            'byCountry' => array_slice($byCountry, 0, 8, true),
            'topRegions' => array_slice($byRegion, 0, 8, true),
            'lowStockItems' => array_slice($lowStock, 0, 10),
            'recentOrders' => $recentOrders,
            // Unknown/uncategorised gets a neutral grey — it's a data gap, not a wine colour.
            'colourSwatch' => fn (string $label) => WineColour::tryFrom($label)?->getSwatch() ?? '#a8a29e',
        ]);
    }
}
