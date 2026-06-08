<?php

declare(strict_types=1);
use Domain\Billing\Enums\Plan;
use Domain\Company\Models\Company;
use Domain\User\Enums\Role;
use Domain\User\Models\User;
use Domain\Venue\Actions\SyncUserVenuesAction;
use Domain\Venue\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

/**
 * Build a coherent tenant: a company on the given plan, a user with the given
 * role, and one venue the user is assigned to.
 *
 * @return array{0: Company, 1: User, 2: Venue}
 */
function makeTenant(Plan $plan = Plan::Free, Role $role = Role::Owner): array
{
    $company = Company::factory()->onPlan($plan)->create();
    $user = User::factory()->role($role)->create(['company_id' => $company->id]);
    $venue = Venue::factory()->create(['company_id' => $company->id]);
    (new SyncUserVenuesAction)->execute($user->id, [$venue->id]);

    return [$company, $user, $venue];
}

/**
 * A user whose company is on the given plan (no venue) — for feature-gating tests.
 */
function userOnPlan(Plan $plan, Role $role = Role::Owner): User
{
    return User::factory()->role($role)->create([
        'company_id' => Company::factory()->onPlan($plan),
    ]);
}
