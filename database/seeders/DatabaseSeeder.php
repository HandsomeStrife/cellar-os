<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Supplier\Enums\SupplierDocumentStatus;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierDocument;
use Domain\Supplier\Models\SupplierUser;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** @var array<string, Supplier> */
    private array $suppliers = [];

    /** @var array<string, Product> */
    private array $products = [];

    /**
     * Idempotent demo dataset (safe to re-run). Seeds a default admin plus four
     * demo users at different points in their journey, each on a different plan.
     * Catalogue and suppliers are shared across the app; venues, inventory and
     * orders are per-user.
     */
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedSuppliers();
        $this->seedCatalogue();

        $this->seedFreeUser();
        $this->seedStarterUser();
        $this->seedProUser();
        $this->seedGroupUser();

        $this->seedSupplierPortal();
    }

    private function seedAdmin(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@cellaros.test'],
            ['name' => 'CellarOS Admin', 'password' => Hash::make('password')],
        );
    }

    private function seedSuppliers(): void
    {
        // Deterministic so the seeder runs without Faker (a dev-only dependency).
        $details = [
            'Bordeaux Imports' => ['Camille Laurent', 'Bordeaux, France'],
            'Italian Fine Wines' => ['Marco Bianchi', 'Milan, Italy'],
            'New World Selections' => ['Sarah Mitchell', 'London, United Kingdom'],
        ];

        foreach ($details as $name => [$contact, $location]) {
            $this->suppliers[$name] = Supplier::firstOrCreate(
                ['name' => $name],
                [
                    'contact' => $contact,
                    'email' => str(str($name)->slug())->append('@example.test')->value(),
                    'location' => $location,
                    'status' => SupplierStatus::Active->value,
                ],
            );
        }
    }

    private function seedCatalogue(): void
    {
        // wine_name, producer, country, region, colour, vintage, price, lat, lng, supplier
        $wines = [
            ['Chablis Premier Cru', 'Domaine Laroche', 'France', 'Burgundy', WineColour::White, 2021, '28.50', '47.82', '3.80', 'Bordeaux Imports'],
            ['Sancerre Les Monts', 'Henri Bourgeois', 'France', 'Loire', WineColour::White, 2022, '22.00', '47.33', '2.84', 'Bordeaux Imports'],
            ['Provence Rosé', 'Whispering Angel', 'France', 'Provence', WineColour::Rose, 2023, '19.50', '43.53', '6.45', 'Bordeaux Imports'],
            ['Champagne Brut Réserve', 'Pol Roger', 'France', 'Champagne', WineColour::Sparkling, null, '55.00', '49.04', '4.02', 'Bordeaux Imports'],
            ['Barolo Riserva', 'Giacomo Conterno', 'Italy', 'Piedmont', WineColour::Red, 2017, '92.00', '44.61', '7.94', 'Italian Fine Wines'],
            ['Brunello di Montalcino', 'Biondi-Santi', 'Italy', 'Tuscany', WineColour::Red, 2018, '120.00', '43.06', '11.49', 'Italian Fine Wines'],
            ['Rioja Gran Reserva', 'La Rioja Alta', 'Spain', 'Rioja', WineColour::Red, 2015, '45.00', '42.46', '-2.45', 'New World Selections'],
            ['Vintage Port', 'Taylor Fladgate', 'Portugal', 'Douro', WineColour::Fortified, 2016, '68.00', '41.16', '-7.79', 'New World Selections'],
            ['Napa Cabernet Sauvignon', 'Stag\'s Leap', 'United States', 'Napa Valley', WineColour::Red, 2019, '75.00', '38.50', '-122.27', 'New World Selections'],
            ['Marlborough Sauvignon Blanc', 'Cloudy Bay', 'New Zealand', 'Marlborough', WineColour::White, 2023, '21.00', '-41.51', '173.86', 'New World Selections'],
        ];

        foreach ($wines as $i => [$name, $producer, $country, $region, $colour, $vintage, $price, $lat, $lng, $supplierName]) {
            $this->products[$name] = Product::firstOrCreate(
                ['wine_name' => $name, 'supplier_id' => $this->suppliers[$supplierName]->id],
                [
                    'producer' => $producer,
                    'country' => $country,
                    'region' => $region,
                    'colour' => $colour,
                    'vintage' => $vintage,
                    'unit_price' => $price,
                    'price_per_litre' => number_format((float) $price / 0.75, 2, '.', ''),
                    'format_ml' => 750,
                    'case_size' => 6,
                    'stock' => 12 + ($i * 4), // deterministic 12..48
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
            );
        }
    }

    /** Free plan, just signed up: a company + owner + venue but no stock or orders yet. */
    private function seedFreeUser(): void
    {
        $company = $this->company('Harbourview Hospitality', Plan::Free);
        $owner = $this->owner($company, 'free@cellaros.test', 'Olivia Newbury');
        $venue = $this->venue($company, 'Harbourview Bistro', 'Brighton');
        $this->assignVenues($owner, [$venue->id]);
    }

    /** Starter plan, getting going: a couple of orders, a little received stock. */
    private function seedStarterUser(): void
    {
        $company = $this->company('Tasting Room Wines', Plan::Starter);
        $owner = $this->owner($company, 'starter@cellaros.test', 'Marcus Trent');
        $venue = $this->venue($company, 'The Tasting Room', 'Bristol');
        $this->assignVenues($owner, [$venue->id]);

        $this->inventory($venue, 'Sancerre Les Monts', 18, 6);
        $this->inventory($venue, 'Provence Rosé', 12, 9);

        $this->order($owner, $venue, 'Bordeaux Imports', OrderStatus::Draft, 'First order: house whites and rosé.', [
            'Chablis Premier Cru' => 12,
            'Provence Rosé' => 6,
        ]);
        $this->order($owner, $venue, 'New World Selections', OrderStatus::Sent, 'New World reds for the by-the-glass list.', [
            'Rioja Gran Reserva' => 6,
        ]);
    }

    /** Pro plan, fully operational single venue: stock, plus orders across the lifecycle. */
    private function seedProUser(): void
    {
        $company = $this->company('Cellar Door Group', Plan::Pro);
        $owner = $this->owner($company, 'demo@cellaros.test', 'Demo Sommelier');
        $venue = $this->venue($company, 'The Cellar Door', 'London');
        $this->assignVenues($owner, [$venue->id]);

        // wine => [quantity, days since received]
        foreach (['Chablis Premier Cru' => [24, 5], 'Barolo Riserva' => [18, 12], 'Rioja Gran Reserva' => [30, 3], 'Champagne Brut Réserve' => [12, 20]] as $wine => [$qty, $daysAgo]) {
            $this->inventory($venue, $wine, $qty, $daysAgo);
        }

        $this->order($owner, $venue, 'Italian Fine Wines', OrderStatus::Draft, 'Restock Italian reds for the autumn list.', [
            'Barolo Riserva' => 6,
            'Brunello di Montalcino' => 6,
        ]);
        $this->order($owner, $venue, 'Bordeaux Imports', OrderStatus::Sent, 'Champagne for the festive season.', [
            'Champagne Brut Réserve' => 24,
        ]);
        $this->order($owner, $venue, 'New World Selections', OrderStatus::Received, 'Received: New World mixed case.', [
            'Napa Cabernet Sauvignon' => 6,
            'Marlborough Sauvignon Blanc' => 12,
        ]);
    }

    /**
     * Group plan: one company, multiple venues, and a TEAM — an owner who sees
     * everything plus a member scoped to a single venue (showcases user_venue).
     */
    private function seedGroupUser(): void
    {
        $company = $this->company('Anand Restaurant Group', Plan::Group);
        $owner = $this->owner($company, 'group@cellaros.test', 'Priya Anand');

        $hq = $this->venue($company, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($company, 'Riverside Brasserie', 'Leeds');
        $this->assignVenues($owner, [$hq->id, $riverside->id]);

        // A member who can only see the Riverside site.
        $member = $this->teammate($company, 'group.member@cellaros.test', 'Leo Carter', Role::Member);
        $this->assignVenues($member, [$riverside->id]);

        $this->inventory($hq, 'Brunello di Montalcino', 36, 4);
        $this->inventory($hq, 'Vintage Port', 24, 12);
        $this->inventory($riverside, 'Marlborough Sauvignon Blanc', 48, 3);
        $this->inventory($riverside, 'Provence Rosé', 30, 7);

        $this->order($owner, $hq, 'Italian Fine Wines', OrderStatus::Received, 'HQ: Tuscan flagship restock.', [
            'Brunello di Montalcino' => 12,
        ]);
        $this->order($member, $riverside, 'New World Selections', OrderStatus::Draft, 'Riverside: summer whites.', [
            'Marlborough Sauvignon Blanc' => 24,
        ]);
    }

    /**
     * Supplier portal demo: three suppliers at different points in their journey,
     * mirroring the per-plan user journeys. All passwords are `password`.
     *
     *  - Bordeaux Imports (supplier@cellaros.test) — established: a full profile,
     *    two portal users (a team), and documents spanning the lifecycle
     *    (awaiting + analysed).
     *  - Italian Fine Wines (italian-supplier@cellaros.test) — mid-analysis: one
     *    document being analysed and one that failed.
     *  - New World Selections (newworld-supplier@cellaros.test) — just invited:
     *    the user has no password yet (invite pending) and no documents.
     */
    private function seedSupplierPortal(): void
    {
        // Established supplier with a team and documents either side of analysis.
        $bordeaux = $this->suppliers['Bordeaux Imports'];
        $bordeaux->update([
            'phone' => '+33 5 56 00 00 00',
            'website' => 'https://bordeaux-imports.example',
            'address' => '12 Quai des Chartrons',
            'city' => 'Bordeaux',
            'postcode' => '33000',
            'country' => 'France',
        ]);
        $camille = $this->supplierUser($bordeaux, 'supplier@cellaros.test', 'Camille Laurent');
        $this->supplierUser($bordeaux, 'supplier.team@cellaros.test', 'Hugo Marchand');
        $this->supplierDocument($bordeaux, $camille, 'spring-2026-portfolio.csv', 'Spring 2026 portfolio', SupplierDocumentStatus::AwaitingAnalysis);
        $this->supplierDocument($bordeaux, $camille, 'winter-2025-list.xlsx', 'Winter 2025 price list', SupplierDocumentStatus::Analysed, 'Extracted 142 wines.', 6);

        // Mid-analysis supplier: one in progress, one failed.
        $italian = $this->suppliers['Italian Fine Wines'];
        $italian->update([
            'phone' => '+39 02 1234 5678',
            'website' => 'https://italian-fine-wines.example',
            'address' => 'Via Montenapoleone 8',
            'city' => 'Milan',
            'postcode' => '20121',
            'country' => 'Italy',
        ]);
        $marco = $this->supplierUser($italian, 'italian-supplier@cellaros.test', 'Marco Bianchi');
        $this->supplierDocument($italian, $marco, 'piedmont-2026.csv', 'Piedmont 2026 allocation', SupplierDocumentStatus::Analysing);
        $this->supplierDocument($italian, $marco, 'scanned-catalogue.pdf', 'Scanned catalogue', SupplierDocumentStatus::Failed, 'Could not read a usable table from the file.', 2);

        // Freshly invited supplier: invite pending (no password), no documents yet.
        $newWorld = $this->suppliers['New World Selections'];
        $newWorld->update([
            'website' => 'https://new-world-selections.example',
            'city' => 'London',
            'country' => 'United Kingdom',
        ]);
        $this->supplierUser($newWorld, 'newworld-supplier@cellaros.test', 'Sarah Mitchell', invited: true);
    }

    private function supplierUser(Supplier $supplier, string $email, string $name, bool $invited = false): SupplierUser
    {
        return SupplierUser::updateOrCreate(
            ['email' => $email],
            [
                'supplier_id' => $supplier->id,
                'name' => $name,
                // Invited users have no password until they accept the email invite.
                'password' => $invited ? null : Hash::make('password'),
            ],
        );
    }

    private function supplierDocument(
        Supplier $supplier,
        SupplierUser $uploader,
        string $fileName,
        string $title,
        SupplierDocumentStatus $status,
        ?string $notes = null,
        ?int $analysedDaysAgo = null,
    ): void {
        SupplierDocument::firstOrCreate(
            ['supplier_id' => $supplier->id, 'file_name' => $fileName],
            [
                'uploaded_by_supplier_user_id' => $uploader->id,
                'title' => $title,
                'file_type' => str($fileName)->endsWith('.pdf') ? 'application/pdf' : 'text/csv',
                'file_size' => 24_000,
                'storage_path' => 'supplier-documents/demo-'.$fileName,
                'status' => $status->value,
                'analysis_notes' => $notes,
                'analysed_at' => $analysedDaysAgo !== null ? now()->subDays($analysedDaysAgo) : null,
            ],
        );
    }

    private function company(string $name, Plan $plan): Company
    {
        return Company::firstOrCreate(
            ['name' => $name],
            ['plan' => $plan->value, 'base_currency' => 'GBP'],
        );
    }

    private function owner(Company $company, string $email, string $name): User
    {
        return $this->teammate($company, $email, $name, Role::Owner);
    }

    private function teammate(Company $company, string $email, string $name, Role $role): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'company_id' => $company->id,
                'full_name' => $name,
                'password' => Hash::make('password'),
                'role' => $role->value,
            ],
        );
    }

    private function venue(Company $company, string $name, string $city): Venue
    {
        return Venue::firstOrCreate(
            ['company_id' => $company->id, 'name' => $name],
            ['city' => $city, 'country' => 'United Kingdom', 'base_currency' => 'GBP'],
        );
    }

    /**
     * @param  array<int, int>  $venueIds
     */
    private function assignVenues(User $user, array $venueIds): void
    {
        (new SyncUserVenuesAction)->execute($user->id, $venueIds);
    }

    private function inventory(Venue $venue, string $wine, int $qty, int $daysAgo): void
    {
        $product = $this->products[$wine];

        InventoryItem::firstOrCreate(
            ['venue_id' => $venue->id, 'product_id' => $product->id],
            [
                'quantity_units' => $qty,
                'last_purchase_price' => $product->unit_price,
                'last_purchase_currency' => 'GBP',
                'last_received_at' => now()->subDays($daysAgo),
            ],
        );
    }

    /**
     * @param  array<string, int>  $lines  wine name => quantity
     */
    private function order(User $user, Venue $venue, string $supplier, OrderStatus $status, string $notes, array $lines): void
    {
        $order = Order::firstOrCreate(
            ['venue_id' => $venue->id, 'created_by' => $user->id, 'notes' => $notes],
            [
                'company_id' => $venue->company_id,
                'supplier_id' => $this->suppliers[$supplier]->id,
                'status' => $status,
                'total' => 0,
            ],
        );

        if (! $order->wasRecentlyCreated) {
            return;
        }

        $total = 0.0;

        foreach ($lines as $wine => $qty) {
            $product = $this->products[$wine];
            $order->items()->create([
                'product_id' => $product->id,
                'wine_name' => $product->wine_name,
                'quantity_units' => $qty,
                'unit_price_at_order' => $product->unit_price,
                'currency_at_order' => 'GBP',
            ]);
            $total += $qty * (float) $product->unit_price;
        }

        $order->update(['total' => $total]);
    }
}
