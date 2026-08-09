<?php

declare(strict_types=1);

namespace Domain\Billing\Casts;

use Domain\Billing\Enums\Plan;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts `companies.plan` to the Plan enum, tolerating the retired `free` and
 * `starter` values.
 *
 * A plain enum cast throws on an unknown string, which would 500 every request
 * for any row the plan-collapse data migration hasn't reached (a restored
 * backup, a golden import, a stale Stripe webhook). Normalising on read keeps
 * those companies working; the migration then rewrites the column properly.
 *
 * @implements CastsAttributes<Plan, Plan|string|null>
 */
class PlanCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Plan
    {
        return Plan::fromValue(is_string($value) ? $value : null);
    }

    /**
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $plan = $value instanceof Plan ? $value : Plan::fromValue(is_string($value) ? $value : null);

        return [$key => $plan->value];
    }
}
