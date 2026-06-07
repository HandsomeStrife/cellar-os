<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe price IDs per plan
    |--------------------------------------------------------------------------
    | Map each paid plan to its Stripe Price ID. Leave unset locally — the
    | pricing page degrades gracefully and checkout is disabled until set.
    */

    'prices' => [
        'starter' => env('STRIPE_PRICE_STARTER'),
        'pro' => env('STRIPE_PRICE_PRO'),
        'group' => env('STRIPE_PRICE_GROUP'),
    ],

];
