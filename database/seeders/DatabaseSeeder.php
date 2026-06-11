<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\BuildsDemoData;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Models\Supplier;
use Domain\User\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The CLEAN default seed — safe for production. Creates the admin and the four
 * demo companies/users/venues, and (when real supplier catalogues are present,
 * e.g. after `wine:import-golden`) wires the demo journeys to REAL suppliers.
 *
 * It seeds NO fictional suppliers, wines, or portal accounts — that demo
 * content lives in DemoSupplierSeeder (opt-in, local/E2E only):
 *   php artisan db:seed --class=DemoSupplierSeeder
 *
 * Production order matters: migrate:fresh → wine:import-golden → db:seed
 * (so the real catalogues exist for the journeys to attach to).
 */
class DatabaseSeeder extends Seeder
{
    use BuildsDemoData;

    public function run(): void
    {
        $this->seedAdmin();

        $free = $this->company('Harbourview Hospitality', Plan::Free);
        $freeOwner = $this->owner($free, 'free@cellaros.test', 'Olivia Newbury');
        $freeVenue = $this->venue($free, 'Harbourview Bistro', 'Brighton');
        $this->assignVenues($freeOwner, [$freeVenue->id]);

        $starter = $this->company('Tasting Room Wines', Plan::Starter);
        $starterOwner = $this->owner($starter, 'starter@cellaros.test', 'Marcus Trent');
        $starterVenue = $this->venue($starter, 'The Tasting Room', 'Bristol');
        $this->assignVenues($starterOwner, [$starterVenue->id]);

        $pro = $this->company('Cellar Door Group', Plan::Pro);
        $proOwner = $this->owner($pro, 'demo@cellaros.test', 'Demo Sommelier');
        $proVenue = $this->venue($pro, 'The Cellar Door', 'London');
        $this->assignVenues($proOwner, [$proVenue->id]);

        $group = $this->company('Anand Restaurant Group', Plan::Group);
        $groupOwner = $this->owner($group, 'group@cellaros.test', 'Priya Anand');
        $hq = $this->venue($group, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($group, 'Riverside Brasserie', 'Leeds');
        $this->assignVenues($groupOwner, [$hq->id, $riverside->id]);
        $member = $this->teammate($group, 'group.member@cellaros.test', 'Leo Carter', Role::Member);
        $this->assignVenues($member, [$riverside->id]);

        $this->seedRealJourneys();
    }

    private function seedAdmin(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@cellaros.test'],
            ['name' => 'CellarOS Admin', 'password' => Hash::make('password')],
        );
    }

    /**
     * When real (golden-imported) supplier catalogues exist, give the demo
     * companies meaningful journeys against them: connections, venue
     * allocations, inventory and orders built from real priced wines. On a
     * bare database (no golden yet) this is a clean no-op — the demo accounts
     * simply start empty.
     */
    private function seedRealJourneys(): void
    {
        // The public suppliers with the largest PRICED catalogues, deterministically.
        $counts = Product::whereNotNull('unit_price')
            ->whereNotNull('supplier_id')
            ->select('supplier_id', DB::raw('count(*) as wines'))
            ->groupBy('supplier_id')
            ->orderByDesc('wines')
            ->pluck('wines', 'supplier_id');

        $suppliers = Supplier::whereNull('created_by_company_id')
            ->whereIn('id', $counts->keys())
            // Never wire the "real" journeys to the fictional dev-demo suppliers
            // (DemoSupplierSeeder builds its own journeys for those).
            ->whereNotIn('name', DemoSupplierSeeder::FICTIONAL_SUPPLIERS)
            ->get()
            ->sortBy([fn ($a, $b) => $counts[$b->id] <=> $counts[$a->id], fn ($a, $b) => strcmp($a->name, $b->name)])
            ->take(3)
            ->values();

        if ($suppliers->count() < 2) {
            return; // no real catalogue yet — demo accounts start empty
        }

        [$first, $second] = [$suppliers[0], $suppliers[1]];
        $third = $suppliers[2] ?? $second;

        $wines = fn (Supplier $s, int $n) => Product::where('supplier_id', $s->id)
            ->whereNotNull('unit_price')
            ->orderBy('id')
            ->limit($n)
            ->get();

        $starter = $this->company('Tasting Room Wines', Plan::Starter);
        $starterOwner = $this->owner($starter, 'starter@cellaros.test', 'Marcus Trent');
        $starterVenue = $this->venue($starter, 'The Tasting Room', 'Bristol');
        $this->connectSupplier($starter, $first, [$starterVenue]);
        $w = $wines($first, 2);
        if ($w->count() >= 2) {
            $this->inventory($starterVenue, $w[0], 18, 6);
            $this->order($starterOwner, $starterVenue, $first, OrderStatus::Draft, 'First order: by-the-glass restock.', [[$w[0], 12], [$w[1], 6]]);
        }

        $pro = $this->company('Cellar Door Group', Plan::Pro);
        $proOwner = $this->owner($pro, 'demo@cellaros.test', 'Demo Sommelier');
        $proVenue = $this->venue($pro, 'The Cellar Door', 'London');
        $this->connectSupplier($pro, $first, [$proVenue]);
        $this->connectSupplier($pro, $second, [$proVenue]);
        $this->connectSupplier($pro, $third, [$proVenue]);
        $a = $wines($first, 3);
        $b = $wines($second, 2);
        if ($a->count() >= 3 && $b->count() >= 2) {
            $this->inventory($proVenue, $a[0], 24, 5);
            $this->inventory($proVenue, $b[0], 18, 12);
            $this->order($proOwner, $proVenue, $first, OrderStatus::Sent, 'Cellar restock for the autumn list.', [[$a[1], 12], [$a[2], 6]]);
            $this->order($proOwner, $proVenue, $second, OrderStatus::Received, 'Received: fine wine allocation.', [[$b[1], 6]]);
        }

        $group = $this->company('Anand Restaurant Group', Plan::Group);
        $groupOwner = $this->owner($group, 'group@cellaros.test', 'Priya Anand');
        $hq = $this->venue($group, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($group, 'Riverside Brasserie', 'Leeds');
        $this->connectSupplier($group, $second, [$hq]);
        $this->connectSupplier($group, $third, [$riverside]);
        $g = $wines($second, 1);
        $r = $wines($third, 1);
        if ($g->isNotEmpty()) {
            $this->inventory($hq, $g[0], 36, 4);
            $this->order($groupOwner, $hq, $second, OrderStatus::Received, 'HQ: flagship restock.', [[$g[0], 12]]);
        }
        if ($r->isNotEmpty()) {
            $this->inventory($riverside, $r[0], 30, 7);
        }
    }
}
