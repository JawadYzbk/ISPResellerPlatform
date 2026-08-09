<?php

use App\Support\Money;

it('performs integer minor-unit arithmetic without floats', function (): void {
    $total = (new Money(1999, 'USD'))->plus(new Money(1, 'USD'));

    expect($total->amount)->toBe(2000)
        ->and($total->currency)->toBe('USD');
});

it('allocates remainders deterministically', function (): void {
    $parts = (new Money(100, 'JPY'))->allocate(1, 1, 1);

    expect(array_map(fn (Money $money): int => $money->amount, $parts))->toBe([34, 33, 33]);
});

it('supports currencies with three decimal minor units', function (): void {
    $total = (new Money(1250, 'KWD'))->plus(new Money(750, 'KWD'));

    expect($total->amount)->toBe(2000);
});
