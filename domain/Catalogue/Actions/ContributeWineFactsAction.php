<?php

declare(strict_types=1);

namespace Domain\Catalogue\Actions;

use Domain\Catalogue\Data\ProductData;
use Domain\Catalogue\Models\WineFact;
use Domain\Catalogue\Support\WineIdentity;
use Domain\Shared\Actions\AbstractAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Feeds a product's objective attributes (grape, colour, origin — NEVER prices)
 * into the shared wine-facts store. Fill-don't-overwrite: the first observed
 * value of a field wins; a later DISAGREEING observation marks the field
 * contested (contested fields are withheld from display). The contributing
 * supplier is recorded per field for internal audit only — never shown.
 *
 * Best-effort by design: a facts-store hiccup must never break the customer's
 * import/approval, so all failures are swallowed (unique-key races retry once,
 * turning the insert into a fill).
 */
class ContributeWineFactsAction extends AbstractAction
{
    private const FACT_FIELDS = ['country', 'region', 'sub_region', 'grape', 'colour'];

    public function execute(ProductData $product): void
    {
        try {
            $this->contribute($product);
        } catch (QueryException) {
            // Unique-key race: another contribution created this identity
            // between firstOrNew and save — retry once as a fill.
            try {
                $this->contribute($product);
            } catch (Throwable $e) {
                report($e);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function contribute(ProductData $product): void
    {
        $key = WineIdentity::keyFor($product->producer, $product->wine_name);

        if ($key === null) {
            return; // no (real) producer → identity too ambiguous to share facts
        }

        $fact = WineFact::firstOrNew(['identity_key' => $key]);

        if (! $fact->exists) {
            $fact->wine_name = $product->wine_name;
            $fact->producer = $product->producer;
            $fact->observations = 0;
        }

        $sources = $fact->field_sources ?? [];
        $conflicts = $fact->field_conflicts ?? [];
        $changed = ! $fact->exists;

        foreach (self::FACT_FIELDS as $field) {
            $incoming = $product->{$field};

            if ($this->isEmpty($incoming)) {
                continue;
            }

            if ($this->isEmpty($fact->{$field})) {
                $fact->{$field} = $incoming;
                $sources[$field] = [
                    'supplier_id' => $product->supplier_id,
                    'observed_at' => Carbon::now()->toIso8601String(),
                ];
                $changed = true;
            } elseif (! $this->sameValue($fact->{$field}, $incoming)) {
                // Disagreement: keep the stored value but flag the field as
                // contested so it is no longer displayed as a reliable fact.
                $conflicts[$field] = ($conflicts[$field] ?? 0) + 1;
                $changed = true;
            }
        }

        if (! $changed) {
            return; // pure re-observation — skip the write entirely
        }

        $fact->field_sources = $sources;
        $fact->field_conflicts = $conflicts;
        $fact->observations = ($fact->observations ?? 0) + 1;
        $fact->save();
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function sameValue(mixed $stored, mixed $incoming): bool
    {
        $normalise = function (mixed $value): string {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            if (is_array($value)) {
                $value = array_map(fn ($v) => mb_strtolower(trim((string) $v)), $value);
                sort($value);

                return json_encode($value) ?: '';
            }

            return mb_strtolower(trim((string) $value));
        };

        return $normalise($stored) === $normalise($incoming);
    }
}
