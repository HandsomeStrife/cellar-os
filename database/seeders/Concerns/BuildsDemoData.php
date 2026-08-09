<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Domain\Billing\Enums\Plan;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Inventory\Models\InventoryItem;
use Domain\Order\Enums\OrderStatus;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Shared, idempotent builders used by both the clean default seeder and the
 * fictional demo-content seeder.
 */
trait BuildsDemoData
{
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

    /**
     * Connect a company to a (shared) supplier and allocate it to venues.
     *
     * @param  array<int, Venue>  $venues
     */
    private function connectSupplier(Company $company, Supplier $supplier, array $venues = []): void
    {
        DB::table('company_supplier')->insertOrIgnore([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($venues as $venue) {
            DB::table('supplier_venue')->insertOrIgnore([
                'supplier_id' => $supplier->id,
                'venue_id' => $venue->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function inventory(Venue $venue, Product $product, int $qty, int $daysAgo): void
    {
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
     * @param  array<int, array{0: Product, 1: int}>  $lines  [product, quantity]
     */
    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     * @param  float  $priceDrift  multiplier on the snapshot price, so a seeded
     *                             order can look like it was placed when prices
     *                             were different — which is what makes "repeat
     *                             this order" show its change report.
     */
    private function order(
        User $user,
        Venue $venue,
        Supplier $supplier,
        OrderStatus $status,
        string $notes,
        array $lines,
        float $priceDrift = 1.0,
    ): void {
        $order = Order::firstOrCreate(
            ['venue_id' => $venue->id, 'created_by' => $user->id, 'notes' => $notes],
            [
                'company_id' => $venue->company_id,
                'supplier_id' => $supplier->id,
                'status' => $status,
                'total' => 0,
                // Seeded orders get a real PO number, like every order raised
                // through the app — a demo showing "#D9711FEF" looks broken.
                'po_number' => $this->nextDemoPoNumber($venue->company_id),
            ],
        );

        if (! $order->wasRecentlyCreated) {
            return;
        }

        $total = 0.0;

        foreach ($lines as [$product, $qty]) {
            $price = number_format((float) $product->unit_price * $priceDrift, 2, '.', '');

            $order->items()->create([
                'product_id' => $product->id,
                'wine_name' => $product->wine_name,
                'quantity_units' => $qty,
                'unit_price_at_order' => $price,
                'currency_at_order' => 'GBP',
                'sold_by_at_order' => $product->sold_by?->value ?? 'bottle',
            ]);
            $total += $qty * (float) $price;
        }

        $order->update(['total' => $total]);
    }

    /** Next PO number for the company, matching CreateOrderAction's format. */
    private function nextDemoPoNumber(?int $companyId): string
    {
        $prefix = 'PO-'.now()->format('Y').'-';

        $latest = Order::query()
            ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        return sprintf('%s%04d', $prefix, $latest !== null ? (int) substr($latest, strlen($prefix)) + 1 : 1);
    }
}
