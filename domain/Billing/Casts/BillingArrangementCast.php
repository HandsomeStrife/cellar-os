<?php

declare(strict_types=1);

namespace Domain\Billing\Casts;

use Domain\Billing\Enums\BillingArrangement;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts `companies.billing_arrangement`, tolerating a value we can't read.
 *
 * Same reasoning as {@see PlanCast}: a plain enum cast throws a ValueError on
 * READ, which 500s every page for that tenant — including the admin screen you
 * would go to in order to fix it. An arrangement we don't recognise falls back
 * to Standard, which is the conservative reading (they pay list price) and
 * leaves the row visible and correctable.
 *
 * @implements CastsAttributes<BillingArrangement, BillingArrangement|string|null>
 */
class BillingArrangementCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): BillingArrangement
    {
        return (is_string($value) ? BillingArrangement::tryFrom($value) : null)
            ?? BillingArrangement::Standard;
    }

    /**
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $arrangement = $value instanceof BillingArrangement
            ? $value
            : ((is_string($value) ? BillingArrangement::tryFrom($value) : null) ?? BillingArrangement::Standard);

        return [$key => $arrangement->value];
    }
}
