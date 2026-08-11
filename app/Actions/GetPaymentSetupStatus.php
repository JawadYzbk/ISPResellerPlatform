<?php

namespace App\Actions;

use App\Contracts\Action;

final readonly class GetPaymentSetupStatus implements Action
{
    /** @return array{cash: array{ready: bool, status: string, detail: string}, stripe: array{ready: bool, status: string, detail: string}, whish: array{ready: bool, status: string, detail: string}} */
    public function handle(): array
    {
        $stripeSelected = (string) config('services.payments.driver', 'null') === 'stripe';
        $stripeReady = $stripeSelected && collect(['secret', 'publishable_key', 'endpoint', 'webhook_secret'])
            ->every(fn (string $key): bool => $this->configured(config('services.stripe.'.$key)));
        $whishEnabled = (bool) config('services.whish.enabled', false);
        $whishReady = $whishEnabled && collect(['channel', 'secret', 'website_url'])
            ->every(fn (string $key): bool => $this->configured(config('services.whish.'.$key)));

        return [
            'cash' => [
                'ready' => true,
                'status' => 'available',
                'detail' => 'Available to authorized staff after opening a cash shift.',
            ],
            'stripe' => [
                'ready' => $stripeReady,
                'status' => $stripeReady ? 'configured' : ($stripeSelected ? 'not_configured' : 'not_selected'),
                'detail' => $stripeReady
                    ? 'PaymentIntent checkout and webhook settlement are configured.'
                    : ($stripeSelected
                        ? 'Complete the Stripe secret, publishable key, endpoint, and webhook secret.'
                        : 'Stripe is not selected; cash collection remains available.'),
            ],
            'whish' => [
                'ready' => $whishReady,
                'status' => $whishReady ? 'configured' : ($whishEnabled ? 'not_configured' : 'disabled'),
                'detail' => $whishReady
                    ? 'Whish Pay QR and provider-verified callbacks are configured.'
                    : ($whishEnabled
                        ? 'Complete the Whish channel, secret, and website URL.'
                        : 'Whish Pay is disabled; enable it after merchant acceptance.'),
            ],
        ];
    }

    private function configured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
