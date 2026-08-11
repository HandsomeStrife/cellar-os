<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Domain\Catalogue\Models\Product;
use Domain\Company\Models\Company;
use Domain\Order\Models\Order;
use Domain\Supplier\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts the demo back to a known-good state, so a walkthrough can be given
 * twice in one afternoon.
 *
 * It tears down ONLY the demo tenants and their private merchants, then
 * re-seeds. Real catalogues, real suppliers and any other company's data are
 * never touched — the demo merchants are private to the demo companies, which
 * is what makes this safe to run anywhere.
 */
class ResetDemo extends Command
{
    protected $signature = 'demo:reset {--force : skip the confirmation}';

    protected $description = 'Reset the demo companies, suppliers and catalogue to a known-good state.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Wipe and rebuild the demo accounts and their wines?', true)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $this->components->task('Removing the old demo data', fn () => $this->teardown());
        $this->components->task('Rebuilding the demo', function () {
            $this->callSilent('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

            return true;
        });

        $this->newLine();
        $this->line('<fg=green>The demo is ready.</> Every account uses the password <options=bold>password</>.');
        $this->newLine();

        $this->table(
            ['Sign in at', 'Email', 'Shows'],
            [
                ['/login', 'demo@cellaros.test', 'Pro — one venue, three merchants, the full buying journey'],
                ['/login', 'group@cellaros.test', 'Group — two venues, per-venue merchants, a team'],
                ['/login', 'group.member@cellaros.test', 'A team member who can only see the Riverside venue'],
                ['/login', 'trade@cellaros.test', 'Every real supplier we hold a list for — the parsed catalogue at full size'],
                ['/admin', 'admin@cellaros.test', 'Back office — suppliers, parsing, costs, impersonation'],
            ],
        );

        $this->newLine();
        $this->line('Walkthrough: <options=bold>docs/demo/README.md</>');

        return self::SUCCESS;
    }

    /**
     * Delete the demo tenants and their private merchants.
     *
     * Order matters: orders and products reference suppliers with
     * `nullOnDelete`, so they're cleared explicitly rather than left behind as
     * rows pointing at nothing. Deleting the companies then cascades their
     * users, venues and inventory.
     */
    private function teardown(): bool
    {
        $companyIds = Company::whereIn('name', DemoSeeder::COMPANIES)->pluck('id');
        $supplierIds = Supplier::whereIn('name', DemoSeeder::SUPPLIERS)->pluck('id');

        DB::transaction(function () use ($companyIds, $supplierIds) {
            if ($companyIds->isNotEmpty()) {
                Order::whereIn('company_id', $companyIds)->each(function (Order $order) {
                    $order->items()->delete();
                    $order->delete();
                });
            }

            if ($supplierIds->isNotEmpty()) {
                // Documents and their parsed rows, then the demo wines.
                DB::table('parsed_wines')
                    ->whereIn('supplier_document_id', DB::table('supplier_documents')->select('id')->whereIn('supplier_id', $supplierIds))
                    ->delete();
                DB::table('supplier_documents')->whereIn('supplier_id', $supplierIds)->delete();
                DB::table('supplier_parse_profiles')->whereIn('supplier_id', $supplierIds)->delete();
                DB::table('supplier_notes')->whereIn('supplier_id', $supplierIds)->delete();

                Product::whereIn('supplier_id', $supplierIds)->delete();

                DB::table('company_supplier')->whereIn('supplier_id', $supplierIds)->delete();
                DB::table('supplier_venue')->whereIn('supplier_id', $supplierIds)->delete();
                Supplier::whereIn('id', $supplierIds)->delete();
            }

            // Cascades users, venues and inventory.
            Company::whereIn('id', $companyIds)->delete();
        });

        return true;
    }
}
