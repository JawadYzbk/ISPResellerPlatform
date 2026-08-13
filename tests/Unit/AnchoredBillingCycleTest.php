<?php

use App\Domain\Billing\AnchoredBillingCycle;
use Carbon\CarbonImmutable;

it('finds the next local billing anchor and clamps short months', function (): void {
    $cycle = new AnchoredBillingCycle(31);

    expect($cycle->nextAnchorAfter(CarbonImmutable::parse('2026-01-12 09:00:00', 'Asia/Beirut'))->toDateTimeString())
        ->toBe('2026-01-31 23:59:59')
        ->and($cycle->nextAnchorAfter(CarbonImmutable::parse('2026-01-31 23:59:59', 'Asia/Beirut'))->toDateTimeString())
        ->toBe('2026-02-28 23:59:59');
});

it('quotes an inclusive first period with half-up integer proration', function (): void {
    $quote = (new AnchoredBillingCycle(1))->quote(
        CarbonImmutable::parse('2026-08-13 10:00:00', 'Asia/Beirut'),
        31_000,
        'lbp',
    );

    expect($quote->endsAt->toDateTimeString())->toBe('2026-09-01 23:59:59')
        ->and($quote->billableDays)->toBe(19)
        ->and($quote->cycleDays)->toBe(31)
        ->and($quote->proratedAmount)->toBe(19_000)
        ->and($quote->currency)->toBe('LBP');
});
