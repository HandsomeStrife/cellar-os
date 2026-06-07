<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\User\Repositories\UserRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a plan feature, mirroring the upstream <UpgradeGate>.
 *
 * Usage: ->middleware('feature:createPOs')
 * The argument is a Feature enum value (see Domain\Billing\Enums\Feature).
 */
class EnsureFeatureAccess
{
    public function __construct(private readonly UserRepository $users) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $featureEnum = Feature::tryFrom($feature);

        if ($featureEnum === null) {
            abort(500, "Unknown gated feature [{$feature}].");
        }

        $user = $this->users->getLoggedInUser();
        $plan = $user?->plan ?? Plan::Free;

        if (! $plan->can($featureEnum)) {
            return redirect()
                ->route('pricing')
                ->with('upgrade_required', $featureEnum->value);
        }

        return $next($request);
    }
}
