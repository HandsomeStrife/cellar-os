<?php

declare(strict_types=1);

namespace Domain\Billing\Casts;

use Domain\Billing\Enums\BillingInterval;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts `companies.custom_price_interval`, tolerating a value we can't read.
 *
 * Nullable, so an unreadable interval becomes "not stated" rather than a
 * ValueError on read. The price still displays; it just doesn't claim a
 * frequency it can't vouch for. See {@see BillingArrangementCast}.
 *
 * @implements CastsAttributes<BillingInterval|null, BillingInterval|string|null>
 */
class BillingIntervalCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BillingInterval
    {
        return is_string($value) ? BillingInterval::tryFrom($value) : null;
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $interval = $value instanceof BillingInterval
            ? $value
            : (is_string($value) ? BillingInterval::tryFrom($value) : null);

        return [$key => $interval?->value];
    }
}
