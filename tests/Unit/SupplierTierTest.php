<?php

declare(strict_types=1);

use Domain\Supplier\Enums\SupplierTier;

it('derives Private when a company created it and it is not onboarded', function () {
    expect(SupplierTier::derive(5, null))->toBe(SupplierTier::Private);
});

it('derives Listed when public and not onboarded', function () {
    expect(SupplierTier::derive(null, null))->toBe(SupplierTier::Listed);
});

it('derives Onboarded whenever onboarded_at is set', function () {
    expect(SupplierTier::derive(null, now()))->toBe(SupplierTier::Onboarded)
        ->and(SupplierTier::derive(5, now()))->toBe(SupplierTier::Onboarded);
});

it('treats only Private as non-public', function () {
    expect(SupplierTier::Private->isPublic())->toBeFalse()
        ->and(SupplierTier::Listed->isPublic())->toBeTrue()
        ->and(SupplierTier::Onboarded->isPublic())->toBeTrue();
});
