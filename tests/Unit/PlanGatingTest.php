<?php

declare(strict_types=1);

use Domain\Billing\Enums\Feature;
use Domain\Billing\Enums\Plan;

it('orders plans by rank', function () {
    expect(Plan::Free->atLeast(Plan::Free))->toBeTrue()
        ->and(Plan::Pro->atLeast(Plan::Starter))->toBeTrue()
        ->and(Plan::Starter->atLeast(Plan::Pro))->toBeFalse();
});

it('gates features by minimum plan', function () {
    // createPOs requires Starter
    expect(Plan::Free->can(Feature::CreatePurchaseOrders))->toBeFalse()
        ->and(Plan::Starter->can(Feature::CreatePurchaseOrders))->toBeTrue();

    // pdfImport requires Pro
    expect(Plan::Starter->can(Feature::PdfImport))->toBeFalse()
        ->and(Plan::Pro->can(Feature::PdfImport))->toBeTrue();

    // multiVenue requires Group
    expect(Plan::Pro->can(Feature::MultiVenue))->toBeFalse()
        ->and(Plan::Group->can(Feature::MultiVenue))->toBeTrue();
});
