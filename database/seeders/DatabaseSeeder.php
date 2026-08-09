<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Concerns\BuildsDemoData;
use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\User\Enums\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The CLEAN default seed — safe for production. It creates the administrator
 * and the two demo tenants (companies, users, venues) and NOTHING else: no
 * suppliers, no wines, no orders.
 *
 * The demo CONTENT — merchants, catalogue, orders, inventory, portal accounts —
 * is built by {@see DemoSeeder}, which invents its merchants rather than
 * borrowing real ones. Run it (or re-run it) with:
 *
 *   php artisan demo:reset
 *
 * That separation is deliberate. Demo data used to be wired to whichever real
 * suppliers this environment had parsed, which made the demo depend on real
 * trading relationships and made it impossible to stage a scenario — you
 * couldn't set a price to show a comparison, or withhold one to show POA,
 * without editing a real supplier's catalogue. DemoSeeder's merchants are
 * fictional and private to the demo companies, so the whole story can be
 * arranged, and no other tenant can see any of it.
 *
 * Production order: migrate --force → wine:import-golden → db:seed → demo:reset.
 */
class DatabaseSeeder extends Seeder
{
    use BuildsDemoData;

    public function run(): void
    {
        $this->seedAdmin();

        // Two demo accounts, one per plan: Pro (a single venue) and Group
        // (multiple venues plus a venue-scoped team member).
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
    }

    private function seedAdmin(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@cellaros.test'],
            ['name' => 'CellarOS Admin', 'password' => Hash::make('password')],
        );
    }
}
