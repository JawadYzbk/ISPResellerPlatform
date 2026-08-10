<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Billing\BillingPeriod;
use App\Models\Service;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Crypt;

final readonly class PreviewServiceRenewal implements Action
{
    /** @return array<string, mixed> */
    public function handle(Service $service, int $periods = 1): array
    {
        if ($periods < 1 || $periods > 12) {
            throw new DomainException('Renewal periods must be between one and twelve.');
        }

        $service->loadMissing(['customer', 'plan', 'tenant']);
        if ($service->status->value === 'terminated') {
            throw new DomainException('Terminated services require an explicit reactivation workflow.');
        }
        $price = $service->plan?->priceAt();
        if ($price === null) {
            throw new DomainException('The service plan has no current price.');
        }

        $settings = $service->tenant->settingsData();
        $now = CarbonImmutable::now($settings->timezone);
        $expiresAt = $service->expires_at === null ? null : CarbonImmutable::instance($service->expires_at);
        $renewedUntil = $expiresAt;
        for ($period = 0; $period < $periods; $period++) {
            $billingPeriod = BillingPeriod::custom($renewedUntil ?? $now, max(1, (int) $service->plan->duration_days));
            $renewedUntil = $billingPeriod->renewFrom(
                $now,
                $renewedUntil,
                (bool) ($settings->settings['grace_extends_period'] ?? false),
            );
        }

        $previewExpiresAt = $now->addMinutes(10);
        $token = [
            'tenant_id' => $service->tenant_id,
            'service_id' => $service->id,
            'service_public_id' => $service->public_id,
            'plan_id' => $service->plan_id,
            'periods' => $periods,
            'amount' => $price->amount_minor * $periods,
            'currency' => $price->currency,
            'expires_at' => $previewExpiresAt->timestamp,
        ];

        return [
            'preview_id' => Crypt::encryptString(json_encode($token, JSON_THROW_ON_ERROR)),
            'service_id' => $service->public_id,
            'plan_id' => $service->plan->public_id,
            'periods' => $periods,
            'amount' => $price->amount_minor * $periods,
            'currency' => $price->currency,
            'current_expires_at' => $service->expires_at?->toIso8601String(),
            'new_expires_at' => $renewedUntil?->toIso8601String(),
            'preview_expires_at' => $previewExpiresAt->toIso8601String(),
        ];
    }
}
