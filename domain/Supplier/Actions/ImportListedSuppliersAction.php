<?php

declare(strict_types=1);

namespace Domain\Supplier\Actions;

use Domain\Shared\Actions\AbstractAction;
use Domain\Supplier\Enums\SupplierStatus;
use Domain\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;

/**
 * Imports/refreshes PUBLIC (Listed/Onboarded) suppliers from a golden-snapshot
 * or ingestion payload, keyed by name. Idempotent; never touches any company's
 * private supplier records.
 */
class ImportListedSuppliersAction extends AbstractAction
{
    private const FIELDS = ['contact', 'email', 'phone', 'location', 'address', 'city', 'postcode', 'country', 'website', 'status', 'onboarded_at'];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{count: int, ids: array<string, int>} ids = public supplier name => id
     */
    public function execute(array $rows): array
    {
        $count = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $attributes = array_intersect_key($row, array_flip(self::FIELDS));
            $attributes['status'] = SupplierStatus::tryFrom((string) ($attributes['status'] ?? ''))?->value ?? SupplierStatus::Active->value;

            if (array_key_exists('onboarded_at', $attributes) && $attributes['onboarded_at'] !== null) {
                try {
                    $attributes['onboarded_at'] = Carbon::parse((string) $attributes['onboarded_at']);
                } catch (\Throwable) {
                    unset($attributes['onboarded_at']); // garbage date — leave existing value alone
                }
            }

            try {
                Supplier::updateOrCreate(
                    ['name' => $name, 'created_by_company_id' => null],
                    $attributes,
                );
                $count++;
            } catch (\Throwable) {
                // malformed row — skip, never abort the import
            }
        }

        $ids = Supplier::whereNull('created_by_company_id')->pluck('id', 'name')->all();

        return ['count' => $count, 'ids' => $ids];
    }
}
