<?php

use App\Domain\Billing\BillingPeriod;
use Carbon\CarbonImmutable;

it('clamps monthly billing to February and returns to the anchor day', function (): void {
    $period = BillingPeriod::monthly(CarbonImmutable::parse('2024-01-31 10:00:00', 'Asia/Beirut'));

    $february = $period->renewFrom(CarbonImmutable::parse('2024-01-31 10:00:00', 'Asia/Beirut'));
    $march = $period->renewFrom($february, $february);

    expect($february->toDateString())->toBe('2024-02-29')
        ->and($march->toDateString())->toBe('2024-03-31');
});

it('handles weekly, custom, early, late, and grace renewals', function (): void {
    $anchor = CarbonImmutable::parse('2026-03-01 09:00:00', 'Asia/Beirut');
    $weekly = BillingPeriod::weekly($anchor);
    $custom = BillingPeriod::custom($anchor, 10);
    $expiry = CarbonImmutable::parse('2026-03-08 09:00:00', 'Asia/Beirut');

    expect($weekly->renewFrom(CarbonImmutable::parse('2026-03-02 09:00:00', 'Asia/Beirut'), $expiry)->toDateString())->toBe('2026-03-15')
        ->and($custom->renewFrom(CarbonImmutable::parse('2026-03-20 09:00:00', 'Asia/Beirut'), $expiry)->toDateString())->toBe('2026-03-30')
        ->and($weekly->renewFrom(CarbonImmutable::parse('2026-03-20 09:00:00', 'Asia/Beirut'), $expiry)->toDateString())->toBe('2026-03-27')
        ->and($weekly->renewFrom(CarbonImmutable::parse('2026-03-20 09:00:00', 'Asia/Beirut'), $expiry, true)->toDateString())->toBe('2026-03-15');
});
