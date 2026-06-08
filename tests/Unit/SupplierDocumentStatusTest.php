<?php

declare(strict_types=1);

use Domain\Supplier\Enums\SupplierDocumentStatus;

it('exposes labels for every status', function () {
    foreach (SupplierDocumentStatus::cases() as $status) {
        expect($status->getLabel())->toBeString()->not->toBe('');
    }
});

it('maps each status to a valid badge colour', function () {
    expect(SupplierDocumentStatus::AwaitingAnalysis->getColour())->toBe('amber')
        ->and(SupplierDocumentStatus::Analysing->getColour())->toBe('blue')
        ->and(SupplierDocumentStatus::Analysed->getColour())->toBe('green')
        ->and(SupplierDocumentStatus::Failed->getColour())->toBe('red');
});

it('builds value => label options', function () {
    $options = SupplierDocumentStatus::options();

    expect($options)->toHaveKey('AwaitingAnalysis')
        ->and($options['AwaitingAnalysis'])->toBe('Awaiting analysis');
});
