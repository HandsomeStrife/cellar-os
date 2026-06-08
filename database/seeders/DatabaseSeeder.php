<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Admin\Models\Admin;
use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\Product;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;
use Domain\User\Models\User;
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
        $this->suppliers = collect([
            'Bordeaux Imports', 'Italian Fine Wines', 'New World Selections',
        ])->mapWithKeys(fn (string $name) => [$name => Supplier::firstOrCreate(
            ['name' => $name],
            [
                'contact' => fake()->name(),
                'email' => str(str($name)->slug())->append('@example.test')->value(),
                'location' => fake()->city(),
                'status' => SupplierStatus::Active->value,
            ],
        )])->all();
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

        foreach ($wines as [$name, $producer, $country, $region, $colour, $vintage, $price, $lat, $lng, $supplierName]) {
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
                    'stock' => fake()->numberBetween(6, 60),
                    'latitude' => $lat,
                    'longitude' => $lng,
                ],
            );
        }
    }

    /** Free plan, just signed up: a venue but no stock or orders yet (empty / getting-started state). */
    private function seedFreeUser(): void
    {
        $user = $this->user('free@cellaros.test', 'Olivia Newbury', Plan::Free);
        $this->venue($user, 'Harbourview Bistro', 'Brighton');
    }

    /** Starter plan, getting going: a couple of orders, a little received stock. */
    private function seedStarterUser(): void
    {
        $user = $this->user('starter@cellaros.test', 'Marcus Trent', Plan::Starter);
        $venue = $this->venue($user, 'The Tasting Room', 'Bristol');

        $this->inventory($venue, 'Sancerre Les Monts', 18, 6);
        $this->inventory($venue, 'Provence Rosé', 12, 9);

        $this->order($user, $venue, 'Bordeaux Imports', OrderStatus::Draft, 'First order: house whites and rosé.', [
            'Chablis Premier Cru' => 12,
            'Provence Rosé' => 6,
        ]);
        $this->order($user, $venue, 'New World Selections', OrderStatus::Sent, 'New World reds for the by-the-glass list.', [
            'Rioja Gran Reserva' => 6,
        ]);
    }

    /** Pro plan, fully operational single venue: stock, plus orders across the lifecycle. */
    private function seedProUser(): void
    {
        $user = $this->user('demo@cellaros.test', 'Demo Sommelier', Plan::Pro);
        $venue = $this->venue($user, 'The Cellar Door', 'London');

        foreach (['Chablis Premier Cru' => 24, 'Barolo Riserva' => 18, 'Rioja Gran Reserva' => 30, 'Champagne Brut Réserve' => 12] as $wine => $qty) {
            $this->inventory($venue, $wine, $qty, fake()->numberBetween(1, 30));
        }

        $this->order($user, $venue, 'Italian Fine Wines', OrderStatus::Draft, 'Restock Italian reds for the autumn list.', [
            'Barolo Riserva' => 6,
            'Brunello di Montalcino' => 6,
        ]);
        $this->order($user, $venue, 'Bordeaux Imports', OrderStatus::Sent, 'Champagne for the festive season.', [
            'Champagne Brut Réserve' => 24,
        ]);
        $this->order($user, $venue, 'New World Selections', OrderStatus::Received, 'Received: New World mixed case.', [
            'Napa Cabernet Sauvignon' => 6,
            'Marlborough Sauvignon Blanc' => 12,
        ]);
    }

    /** Group plan: multiple venues, each with its own stock and orders. */
    private function seedGroupUser(): void
    {
        $user = $this->user('group@cellaros.test', 'Priya Anand', Plan::Group);

        $hq = $this->venue($user, 'Group HQ Cellar', 'Manchester');
        $riverside = $this->venue($user, 'Riverside Brasserie', 'Leeds');

        $this->inventory($hq, 'Brunello di Montalcino', 36, 4);
        $this->inventory($hq, 'Vintage Port', 24, 12);
        $this->inventory($riverside, 'Marlborough Sauvignon Blanc', 48, 3);
        $this->inventory($riverside, 'Provence Rosé', 30, 7);

        $this->order($user, $hq, 'Italian Fine Wines', OrderStatus::Received, 'HQ: Tuscan flagship restock.', [
            'Brunello di Montalcino' => 12,
        ]);
        $this->order($user, $riverside, 'New World Selections', OrderStatus::Draft, 'Riverside: summer whites.', [
            'Marlborough Sauvignon Blanc' => 24,
        ]);
    }

    private function user(string $email, string $name, Plan $plan): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'full_name' => $name,
                'password' => Hash::make('password'),
                'role' => 'user',
                'plan' => $plan->value,
            ],
        );
    }

    private function venue(User $user, string $name, string $city): Venue
    {
        return Venue::firstOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            ['city' => $city, 'country' => 'United Kingdom', 'base_currency' => 'GBP'],
        );
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
