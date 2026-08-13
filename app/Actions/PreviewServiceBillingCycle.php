<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Data\BillingCycleQuote;
use App\Domain\Billing\AnchoredBillingCycle;
use App\Models\Service;
use Carbon\CarbonImmutable;
use DomainException;

final readonly class PreviewServiceBillingCycle implements Action
{
    public function handle(Service $service, int $anchorDay, ?CarbonImmutable $at = null): BillingCycleQuote
    {
        if ($service->status->value === 'terminated') {
            throw new DomainException('Terminated services cannot change billing cycles.');
        }

        $service->loadMissing(['plan', 'tenant']);
        $timezone = $service->tenant->settingsData()->timezone;
        $at = ($at ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $price = $service->plan->priceAt($at);
        if ($price === null) {
            throw new DomainException('The current plan has no effective price for this billing-cycle preview.');
        }

        $startsAt = $service->expires_at === null
            ? $at
            : CarbonImmutable::instance($service->expires_at)->setTimezone($timezone);
        if ($startsAt->lessThan($at)) {
            $startsAt = $at;
        }

        return (new AnchoredBillingCycle($anchorDay))->quote($startsAt, $price->amount_minor, $price->currency);
    }
}
