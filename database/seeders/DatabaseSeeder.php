<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\BuildsDemoData;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\PriceState;
use Domain\Catalogue\Models\Product;
use Domain\Order\Enums\OrderStatus;
use Domain\Supplier\Models\Supplier;
use Domain\User\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The CLEAN default seed — safe for production. Creates the admin and the TWO
 * demo companies/users/venues, and (when real supplier catalogues are present,
 * e.g. after `wine:import-golden`) wires the demo journeys to REAL suppliers.
 *
 * It seeds NO fictional suppliers, wines, or portal accounts — that demo
 * content lives in DemoSupplierSeeder (opt-in, local/E2E only):
 *   php artisan db:seed --class=DemoSupplierSeeder
 *
 * Production order matters: migrate:fresh → wine:import-golden → db:seed
 * (so the real catalogues exist for the journeys to attach to).
 *
 * ---------------------------------------------------------------------------
 * WHAT EACH ACCOUNT DEMONSTRATES
 *
 * demo@cellaros.test (Pro, one venue)
 *   catalogue browsing over real trade catalogues · cross-supplier price
 *   comparison (the connected suppliers are chosen so two of them list the same
 *   wine) · wine type + sub-type · POA pricing, where the real data has one ·
 *   orders at every point of the lifecycle, including one to repeat ·
 *   inventory with two low-stock lines so the dashboard alerts have teeth ·
 *   price-list upload and parse review per supplier
 *
 * group@cellaros.test (Group, two venues) + group.member@cellaros.test
 *   everything above across multiple venues · per-venue supplier allocation ·
 *   a team member scoped to one venue only
 *
 * Everything is chosen from the real catalogue rather than invented, so a
 * feature the data can't illustrate simply doesn't appear rather than being
 * faked.
 * ---------------------------------------------------------------------------
 */
class DatabaseSeeder extends Seeder
{
    use BuildsDemoData;

    public function run(): void
    {
        $this->seedAdmin();

        // Two demo accounts only, one per plan: Pro (single venue) and Group
        // (multi-venue + a scoped team member). Between them they must reach
        // every feature we demo — see seedRealJourneys().
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

    /**
     * Choose which real suppliers the demo accounts buy from.
     *
     * Biggest catalogue first, but with one deliberate constraint: at least two
     * of them must list the SAME wine, or the catalogue's cross-supplier price
     * comparison has nothing to show. The overlap is found in the real data
     * rather than manufactured — if none exists we fall back to the largest
     * catalogues and the comparison simply doesn't appear.
     *
     * @param  Collection<int, Supplier>  $candidates  largest catalogue first
     * @return Collection<int, Supplier>
     */
    private function pickSuppliers(Collection $candidates): Collection
    {
        $ids = $candidates->pluck('id');

        // A wine (same name + producer + vintage + format) two of our candidate
        // suppliers both list — exactly what alternativesFor() matches on.
        $overlap = DB::table('products')
            ->whereNull('archived_at')
            ->whereIn('supplier_id', $ids)
            ->whereNotNull('producer')
            ->whereNotNull('unit_price')
            ->select('wine_name', 'producer', 'vintage', 'format_ml')
            ->selectRaw('count(distinct supplier_id) as suppliers')
            ->selectRaw('min(supplier_id) as a, max(supplier_id) as b')
            ->groupBy('wine_name', 'producer', 'vintage', 'format_ml')
            ->havingRaw('count(distinct supplier_id) > 1')
            ->orderBy('wine_name')
            ->first();

        if ($overlap === null) {
            return $candidates->take(3)->values();
        }

        $pair = $candidates->whereIn('id', [$overlap->a, $overlap->b])->values();
        $rest = $candidates->whereNotIn('id', [$overlap->a, $overlap->b])->values();

        return $pair->concat($rest)->take(3)->values();
    }

    /**
     * Stock a couple of wines chosen for what they DEMONSTRATE rather than for
     * their place in the list: a sparkling with a sub-type (so Type/Style shows
     * something), and a POA wine if the connected catalogues carry one.
     *
     * Nothing is invented — if the real data has no example, the demo simply
     * doesn't show that feature.
     *
     * @param  Collection<int, Supplier>  $suppliers
     */
    private function showcase($venue, $owner, Collection $suppliers): void
    {
        $ids = $suppliers->pluck('id')->unique()->all();

        $sparkling = Product::whereIn('supplier_id', $ids)
            ->whereNull('archived_at')
            ->whereNotNull('sub_type')
            ->whereNotNull('unit_price')
            ->orderBy('id')
            ->first();

        if ($sparkling !== null) {
            $this->inventory($venue, $sparkling, 18, 9);
        }

        $poa = Product::whereIn('supplier_id', $ids)
            ->whereNull('archived_at')
            ->where('price_state', '!=', PriceState::Priced->value)
            ->orderBy('id')
            ->first();

        if ($poa !== null) {
            // A POA wine on a sent order: the PO shows POA and says the total
            // excludes it, which is the whole point of the state.
            $this->order(
                $owner,
                $venue,
                $suppliers->firstWhere('id', $poa->supplier_id) ?? $suppliers->first(),
                OrderStatus::Sent,
                'Asking about the allocation wines.',
                [[$poa, 6]],
            );
        }
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

        $candidates = Supplier::whereNull('created_by_company_id')
            ->whereIn('id', $counts->keys())
            // Never wire the "real" journeys to the fictional dev-demo suppliers
            // (DemoSupplierSeeder builds its own journeys for those).
            ->whereNotIn('name', DemoSupplierSeeder::FICTIONAL_SUPPLIERS)
            ->get()
            ->sortBy([fn ($a, $b) => $counts[$b->id] <=> $counts[$a->id], fn ($a, $b) => strcmp($a->name, $b->name)])
            ->values();

        if ($candidates->count() < 2) {
            return; // no real catalogue yet — demo accounts start empty
        }

        $suppliers = $this->pickSuppliers($candidates);

        [$first, $second] = [$suppliers[0], $suppliers[1]];
        $third = $suppliers[2] ?? $second;

        $wines = fn (Supplier $s, int $n) => Product::where('supplier_id', $s->id)
            ->whereNotNull('unit_price')
            ->orderBy('id')
            ->limit($n)
            ->get();

        // PRO — fully operational single venue: three suppliers, stock across
        // the cellar, orders at every point of the lifecycle, low-stock alerts.
        $pro = $this->company('Cellar Door Group', Plan::Pro);
        $proOwner = $this->owner($pro, 'demo@cellaros.test', 'Demo Sommelier');
        $proVenue = $this->venue($pro, 'The Cellar Door', 'London');
        $this->connectSupplier($pro, $first, [$proVenue]);
        $this->connectSupplier($pro, $second, [$proVenue]);
        $this->connectSupplier($pro, $third, [$proVenue]);
        $a = $wines($first, 8);
        $b = $wines($second, 6);
        $c = $wines($third, 3);
        if ($a->count() >= 8 && $b->count() >= 6) {
            // A working cellar: mixed quantities and ages…
            $this->inventory($proVenue, $a[0], 24, 5);
            $this->inventory($proVenue, $a[3], 18, 12);
            $this->inventory($proVenue, $a[5], 36, 3);
            $this->inventory($proVenue, $b[0], 18, 12);
            $this->inventory($proVenue, $b[2], 30, 8);
            // …including two on low stock, so the dashboard alerts have teeth.
            $this->inventory($proVenue, $a[6], 2, 25);
            $this->inventory($proVenue, $b[4], 3, 30);

            $this->order($proOwner, $proVenue, $first, OrderStatus::Draft, 'Autumn list: building the next order.', [[$a[1], 12], [$a[2], 6], [$a[7], 6]]);
            $this->order($proOwner, $proVenue, $first, OrderStatus::Sent, 'Cellar restock for the autumn list.', [[$a[4], 12], [$a[6], 12]]);
            $this->order($proOwner, $proVenue, $second, OrderStatus::Sent, 'Fine wine allocation request.', [[$b[1], 6], [$b[3], 6]]);
            $this->order($proOwner, $proVenue, $second, OrderStatus::Received, 'Received: fine wine allocation.', [[$b[5], 6]]);
        }
        // Put the distinctive wine types in front of a demo audience: a
        // sub-typed sparkling and, where the catalogue has one, a POA wine.
        // Selected from the REAL data — never fabricated.
        $this->showcase($proVenue, $proOwner, collect([$first, $second, $third]));

        if ($c->count() >= 3) {
            $this->order($proOwner, $proVenue, $third, OrderStatus::Received, 'Received: mixed case for the tasting menu.', [[$c[0], 6], [$c[1], 6], [$c[2], 6]]);
        }

        // GROUP — two venues with their own suppliers, stock and orders; the
        // member only ever sees Riverside.
        $group = $this->company('Anand Restaurant Group', Plan::Group);
        $groupOwner = $this->owner($group, 'group@cellaros.test', 'Priya Anand');
        $member = $this->teammate($group, 'group.member@cellaros.test', 'Leo Carter', Role::Member);
        $hq = $this->venue($group, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($group, 'Riverside Brasserie', 'Leeds');
        $this->connectSupplier($group, $first, [$hq, $riverside]);
        $this->connectSupplier($group, $second, [$hq]);
        $this->connectSupplier($group, $third, [$riverside]);
        $g = $wines($second, 4);
        $r = $wines($third, 3);
        $f = $wines($first, 12);
        if ($g->count() >= 4) {
            $this->inventory($hq, $g[0], 36, 4);
            $this->inventory($hq, $g[1], 24, 12);
            $this->order($groupOwner, $hq, $second, OrderStatus::Received, 'HQ: flagship restock.', [[$g[2], 12]]);
            $this->order($groupOwner, $hq, $second, OrderStatus::Sent, 'HQ: cellar plan for December.', [[$g[3], 12], [$g[0], 6]]);
        }
        if ($f->count() >= 12) {
            $this->inventory($hq, $f[9], 48, 6);
            $this->order($groupOwner, $hq, $first, OrderStatus::Draft, 'HQ: considering the new arrivals.', [[$f[10], 12]]);
        }
        if ($r->count() >= 3) {
            $this->inventory($riverside, $r[0], 30, 7);
            $this->inventory($riverside, $r[1], 12, 14);
            $this->order($member, $riverside, $third, OrderStatus::Draft, 'Riverside: summer list ideas.', [[$r[2], 12]]);
        }
    }
}
