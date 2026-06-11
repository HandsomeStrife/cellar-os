<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\Product;
use Domain\Shared\Actions\AbstractAction;

/**
 * Imports normalised wine rows for PUBLIC suppliers from a golden-snapshot or
 * ingestion payload, through the standard idempotent upsert (which also feeds
 * the wine-facts store). Rows referencing unknown suppliers are skipped, never
 * guessed.
 */
class ImportCatalogueWinesAction extends AbstractAction
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $supplierIds  public supplier name => id
     * @return array{imported: int, skipped: int}
     */
    public function execute(array $rows, array $supplierIds): array
    {
        $upsert = new UpsertProductAction;
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $supplierId = $supplierIds[trim((string) ($row['supplier'] ?? ''))] ?? null;
            $wineName = trim((string) ($row['wine_name'] ?? ''));

            if ($supplierId === null || $wineName === '') {
                $skipped++;

                continue;
            }

            try {
                $upsert->execute(ProductData::from([
                    'id' => null,
                    'uuid' => null,
                    'supplier_id' => $supplierId,
                    'raw_upload_id' => null,
                    'wine_name' => $wineName,
                    'producer' => $row['producer'] ?? null,
                    'country' => $row['country'] ?? null,
                    'region' => $row['region'] ?? null,
                    'sub_region' => $row['sub_region'] ?? null,
                    'grape' => is_array($row['grape'] ?? null) ? $row['grape'] : null,
                    'colour' => $row['colour'] ?? null,
                    'vintage' => is_numeric($row['vintage'] ?? null) ? (int) $row['vintage'] : null,
                    'format_ml' => is_numeric($row['format_ml'] ?? null) ? (int) $row['format_ml'] : 750,
                    'case_size' => is_numeric($row['case_size'] ?? null) ? (int) $row['case_size'] : 6,
                    'unit_price' => $row['unit_price'] ?? null,
                    'price_per_litre' => $row['price_per_litre'] ?? null,
                    'stock' => is_numeric($row['stock'] ?? null) ? (int) $row['stock'] : 0,
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]));

                // LWIN links travel with golden (ProductData doesn't carry
                // them — they're reference linkage, not wine attributes).
                if (preg_match('/^\d{7}$/', (string) ($row['lwin'] ?? '')) === 1) {
                    Product::where('supplier_id', $supplierId)
                        ->where('wine_name', $wineName)
                        ->when(is_numeric($row['vintage'] ?? null), fn ($q) => $q->where('vintage', (int) $row['vintage']))
                        ->whereNull('lwin')
                        ->update(['lwin' => $row['lwin'], 'lwin_source' => (string) ($row['lwin_source'] ?? 'golden')]);
                }
                $imported++;
            } catch (\Throwable) {
                $skipped++; // malformed values (bad colour/date/etc.) skip, never abort
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
