<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a product feature flag (config/features.php).
 *
 * Usage: ->middleware('flag:map')
 *
 * A disabled area 404s rather than redirecting — as far as the product is
 * concerned it does not exist yet, even though the code is still shipped.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless((bool) config("features.{$flag}", false), 404);

        return $next($request);
    }
}
