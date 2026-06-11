<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\DemoSupplierSeeder;
use Domain\Catalogue\Models\Product;
use Domain\Catalogue\Models\WineFact;
use Domain\Supplier\Models\Supplier;
use Domain\Supplier\Models\SupplierNote;
use Domain\Supplier\Models\SupplierParseProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Exports the CANONICAL trade data — public (Listed/Onboarded) suppliers, their
 * catalogues, global parse recipes, and the wine-facts store — to versioned
 * JSON on the private disk. The DB becomes disposable: after any
 * migrate:fresh, `wine:import-golden` restores everything without re-parsing
 * (the LLM spend happens once, ever). Tenant data (companies, private
 * suppliers, orders, inventory) is deliberately NOT canonical and not exported.
 *
 * The file format doubles as the /api/ingest payload format.
 */
class ExportGoldenSnapshot extends Command
{
    protected $signature = 'wine:export-golden {--dir=golden : directory on the private disk}';

    protected $description = 'Export canonical suppliers/catalogues/recipes/facts to golden JSON files.';

    public function handle(): int
    {
        $dir = trim((string) $this->option('dir'), '/');

        $suppliers = Supplier::whereNull('created_by_company_id')
            // Fictional dev-demo suppliers (DemoSupplierSeeder) are public in
            // dev but must NEVER enter the canonical snapshot.
            ->whereNotIn('name', DemoSupplierSeeder::FICTIONAL_SUPPLIERS)
            ->orderBy('name')
            ->get();
        $supplierNames = $suppliers->pluck('name', 'id');

        $notesBySupplier = SupplierNote::whereIn('supplier_id', $supplierNames->keys())
            ->orderBy('created_at')
            ->get()
            ->groupBy('supplier_id');

        $supplierRows = $suppliers->map(fn (Supplier $s) => [
            'name' => $s->name,
            'contact' => $s->contact,
            'email' => $s->email,
            'phone' => $s->phone,
            'location' => $s->location,
            'address' => $s->address,
            'city' => $s->city,
            'postcode' => $s->postcode,
            'country' => $s->country,
            'website' => $s->website,
            'status' => $s->status?->value ?? 'Active',
            'onboarded_at' => $s->onboarded_at?->toIso8601String(),
            'notes' => ($notesBySupplier[$s->id] ?? collect())->map(fn (SupplierNote $n) => [
                'note' => $n->note,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all(),
        ])->values();

        $wineRows = Product::whereIn('supplier_id', $supplierNames->keys())
            ->orderBy('id')
            ->get()
            ->map(fn (Product $p) => [
                'supplier' => $supplierNames[$p->supplier_id],
                'wine_name' => $p->wine_name,
                'producer' => $p->producer,
                'country' => $p->country,
                'region' => $p->region,
                'sub_region' => $p->sub_region,
                'grape' => $p->grape,
                'colour' => $p->colour?->value,
                'vintage' => $p->vintage,
                'format_ml' => $p->format_ml,
                'case_size' => $p->case_size,
                'unit_price' => $p->unit_price,
                'price_per_litre' => $p->price_per_litre,
                'stock' => $p->stock,
                'lwin' => $p->lwin,
                'lwin_source' => $p->lwin_source,
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
            ])->values();

        $profileRows = SupplierParseProfile::whereNull('company_id')
            ->where('is_active', true)
            ->whereIn('supplier_id', $supplierNames->keys())
            ->get()
            ->map(fn (SupplierParseProfile $p) => [
                'supplier' => $supplierNames[$p->supplier_id],
                'mode' => $p->mode->value,
                'recipe' => $p->recipe,
                'model' => $p->model,
                'confidence' => $p->confidence,
            ])->values();

        $factRows = WineFact::orderBy('identity_key')->get()->map(fn (WineFact $f) => [
            'identity_key' => $f->identity_key,
            'wine_name' => $f->wine_name,
            'producer' => $f->producer,
            'country' => $f->country,
            'region' => $f->region,
            'sub_region' => $f->sub_region,
            'grape' => $f->grape,
            'colour' => $f->colour?->value,
            'lwin' => $f->lwin,
            'lwin_source' => $f->lwin_source,
            'field_sources' => $f->field_sources ?? [],
            'field_conflicts' => $f->field_conflicts ?? [],
            'observations' => $f->observations,
        ])->values();

        $disk = Storage::disk('local');
        $failures = [];
        $write = function (string $file, $data) use ($disk, $dir, &$failures): void {
            if ($disk->put("{$dir}/{$file}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
                $failures[] = $file;
            }
        };

        $write('suppliers.json', $supplierRows);
        $write('wines.json', $wineRows);
        $write('parse-profiles.json', $profileRows);
        $write('wine-facts.json', $factRows);
        $write('manifest.json', [
            'exported_at' => now()->toIso8601String(),
            'counts' => [
                'suppliers' => $supplierRows->count(),
                'wines' => $wineRows->count(),
                'parse_profiles' => $profileRows->count(),
                'wine_facts' => $factRows->count(),
            ],
        ]);

        if ($failures !== []) {
            $this->error('Failed to write: '.implode(', ', $failures));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Golden snapshot → %s: %d suppliers, %d wines, %d recipes, %d facts.',
            $disk->path($dir),
            $supplierRows->count(),
            $wineRows->count(),
            $profileRows->count(),
            $factRows->count(),
        ));

        return self::SUCCESS;
    }
}
