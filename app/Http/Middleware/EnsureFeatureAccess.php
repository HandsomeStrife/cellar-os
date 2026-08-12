<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;
use Domain\Company\Repositories\CompanyRepository;
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
    public function __construct(private readonly CompanyRepository $companies) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $featureEnum = Feature::tryFrom($feature);

        if ($featureEnum === null) {
            abort(500, "Unknown gated feature [{$feature}].");
        }

        $plan = $this->companies->getLoggedInCompany()?->effectivePlan() ?? Plan::default();

        if (! $plan->can($featureEnum)) {
            // With self-serve plans hidden there is nowhere to upgrade, so send
            // them home rather than to a 404'd pricing page.
            $destination = config('features.pricing') ? 'pricing' : 'dashboard';

            return redirect()
                ->route($destination)
                ->with('upgrade_required', $featureEnum->value);
        }

        return $next($request);
    }
}
