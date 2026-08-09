<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Product feature flags
    |--------------------------------------------------------------------------
    |
    | Areas that are built and tested but deliberately not exposed to users
    | yet. The code stays in place; flipping a flag brings the route, the
    | sidebar entry and every in-app link back at once.
    |
    | Routes carry the `flag:<key>` middleware, so a disabled area 404s even
    | if someone types the URL.
    |
    */

    // Global sourcing map (/map) — hidden 2026-08-09 pending a decision on
    // what it is actually for beyond a demo.
    'map' => (bool) env('FEATURE_MAP', false),

    // Self-serve plan pages, checkout and upgrade prompts (/pricing). Hidden
    // while the plan line-up is being reworked; billing itself still works.
    'pricing' => (bool) env('FEATURE_PRICING', false),

];
