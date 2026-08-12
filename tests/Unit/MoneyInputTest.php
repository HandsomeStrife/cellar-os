<?php

declare(strict_types=1);

use Domain\Shared\Support\MoneyInput;

it('reads a plain decimal amount into minor units', function () {
    expect(MoneyInput::toMinorUnits('49.50'))->toBe(4950)
        ->and(MoneyInput::toMinorUnits('49'))->toBe(4900)
        ->and(MoneyInput::toMinorUnits('0.99'))->toBe(99);
});

it('does not lose a penny to floating point', function () {
    // (int) (49.50 * 100) is 4949 on a binary float. Every one of these is a
    // real undercharge if the conversion truncates instead of rounding.
    foreach (['49.50' => 4950, '1.10' => 110, '8.20' => 820, '1.15' => 115] as $typed => $expected) {
        expect(MoneyInput::toMinorUnits((string) $typed))->toBe($expected);
    }
});

it('ignores a currency symbol and thousands separators', function () {
    expect(MoneyInput::toMinorUnits('£1,299.00'))->toBe(129900)
        ->and(MoneyInput::toMinorUnits('1,299'))->toBe(129900)
        ->and(MoneyInput::toMinorUnits(' 49.50 '))->toBe(4950);
});

it('reads a comma as a decimal point when it is being used as one', function () {
    // "49,50" is how half of Europe writes it. Two digits after the comma
    // means decimal; three means someone typed 1,299 for one thousand.
    expect(MoneyInput::toMinorUnits('49,50'))->toBe(4950)
        ->and(MoneyInput::toMinorUnits('1.299,50'))->toBe(129950);
});

it('keeps "nothing" distinct from "zero"', function () {
    expect(MoneyInput::toMinorUnits(null))->toBeNull()
        ->and(MoneyInput::toMinorUnits(''))->toBeNull()
        ->and(MoneyInput::toMinorUnits('   '))->toBeNull()
        ->and(MoneyInput::toMinorUnits('nonsense'))->toBeNull()
        ->and(MoneyInput::toMinorUnits('0'))->toBe(0);
});

it('refuses amounts that are not real prices', function () {
    // The safety belongs to the money type, not to whichever form calls it.
    expect(MoneyInput::toMinorUnits('-5'))->toBeNull()
        // "1e3" would otherwise have its 'e' stripped and read as 13.
        ->and(MoneyInput::toMinorUnits('1e3'))->toBeNull()
        // Past the ceiling it would overflow the unsignedInteger column.
        ->and(MoneyInput::toMinorUnits('99999999999999'))->toBeNull()
        ->and(MoneyInput::toMinorUnits('10000000.01'))->toBeNull();
});

it('accepts the largest price we allow', function () {
    expect(MoneyInput::toMinorUnits('10000000'))->toBe(MoneyInput::MAX_MINOR_UNITS);
});

it('round-trips back to an editable string', function () {
    expect(MoneyInput::toDecimalString(4950))->toBe('49.50')
        ->and(MoneyInput::toDecimalString(129900))->toBe('1299.00')
        ->and(MoneyInput::toDecimalString(null))->toBe('');
});
