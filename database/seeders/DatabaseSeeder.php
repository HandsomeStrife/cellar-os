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
    /**
     * Idempotent demo dataset (safe to re-run).
     */
    public function run(): void
    {
        $admin = Admin::updateOrCreate(
            ['email' => 'admin@cellaros.test'],
            ['name' => 'CellarOS Admin', 'password' => Hash::make('password')],
        );

        $user = User::updateOrCreate(
            ['email' => 'demo@cellaros.test'],
            [
                'full_name' => 'Demo Sommelier',
                'password' => Hash::make('password'),
                'role' => 'user',
                'plan' => Plan::Pro->value,
            ],
        );

        $venue = Venue::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'The Cellar Door'],
            ['city' => 'London', 'country' => 'United Kingdom', 'base_currency' => 'GBP'],
        );

        $suppliers = collect([
            'Bordeaux Imports', 'Italian Fine Wines', 'New World Selections',
        ])->mapWithKeys(fn (string $name) => [$name => Supplier::firstOrCreate(
            ['name' => $name],
            [
                'contact' => fake()->name(),
                'email' => str(str($name)->slug())->append('@example.test')->value(),
                'location' => fake()->city(),
                'status' => SupplierStatus::Active->value,
            ],
        )]);

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

        $products = [];

        foreach ($wines as [$name, $producer, $country, $region, $colour, $vintage, $price, $lat, $lng, $supplierName]) {
            $products[$name] = Product::firstOrCreate(
                ['wine_name' => $name, 'supplier_id' => $suppliers[$supplierName]->id],
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

        // Some received inventory for the venue.
        foreach (['Chablis Premier Cru', 'Barolo Riserva', 'Rioja Gran Reserva'] as $name) {
            InventoryItem::firstOrCreate(
                ['venue_id' => $venue->id, 'product_id' => $products[$name]->id],
                [
                    'quantity_units' => fake()->numberBetween(6, 36),
                    'last_purchase_price' => $products[$name]->unit_price,
                    'last_purchase_currency' => 'GBP',
                    'last_received_at' => now()->subDays(fake()->numberBetween(1, 30)),
                ],
            );
        }

        // A sample draft order — keyed so re-seeding never duplicates it.
        $order = Order::firstOrCreate(
            [
                'venue_id' => $venue->id,
                'created_by' => $user->id,
                'notes' => 'Restock Italian reds for the autumn list.',
            ],
            [
                'supplier_id' => $suppliers['Italian Fine Wines']->id,
                'status' => OrderStatus::Draft,
                'total' => 0,
            ],
        );

        if ($order->wasRecentlyCreated) {
            $total = 0.0;
            foreach (['Barolo Riserva', 'Brunello di Montalcino'] as $name) {
                $product = $products[$name];
                $qty = 6;
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
}
