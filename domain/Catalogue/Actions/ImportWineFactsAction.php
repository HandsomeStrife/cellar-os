<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Enums\WineColour;
use Domain\Catalogue\Models\WineFact;
use Domain\Shared\Actions\AbstractAction;

/**
 * Exact-restores wine-facts rows from a golden-snapshot or ingestion payload —
 * unlike live contribution this OVERWRITES, because the snapshot carries the
 * authoritative provenance (field_sources) and conflict state that incremental
 * contribution can't reconstruct. Run AFTER wine imports so the restored rows
 * supersede freshly-contributed baselines.
 *
 * Note: field_sources supplier ids are environment-specific audit data; they
 * are restored verbatim and remain internal-only.
 */
class ImportWineFactsAction extends AbstractAction
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function execute(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $key = trim((string) ($row['identity_key'] ?? ''));
            $name = trim((string) ($row['wine_name'] ?? ''));

            if ($key === '' || $name === '') {
                continue;
            }

            try {
                WineFact::updateOrCreate(
                    ['identity_key' => $key],
                    [
                        'wine_name' => $name,
                        'producer' => $row['producer'] ?? null,
                        'country' => $row['country'] ?? null,
                        'region' => $row['region'] ?? null,
                        'sub_region' => $row['sub_region'] ?? null,
                        'grape' => is_array($row['grape'] ?? null) ? $row['grape'] : null,
                        'colour' => WineColour::tryFrom((string) ($row['colour'] ?? ''))?->value,
                        'field_sources' => is_array($row['field_sources'] ?? null) ? $row['field_sources'] : [],
                        'field_conflicts' => is_array($row['field_conflicts'] ?? null) ? $row['field_conflicts'] : [],
                        'observations' => is_numeric($row['observations'] ?? null) ? (int) $row['observations'] : 1,
                    ],
                );
                $count++;
            } catch (\Throwable) {
                // malformed row — skip, never abort the restore
            }
        }

        return $count;
    }
}
