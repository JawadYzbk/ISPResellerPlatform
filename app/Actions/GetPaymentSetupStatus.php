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
                        ? 'Missing deployment values: '.$this->missingConfiguration([
                            'STRIPE_SECRET' => config('services.stripe.secret'),
                            'STRIPE_PUBLISHABLE_KEY' => config('services.stripe.publishable_key'),
                            'STRIPE_WEBHOOK_SECRET' => config('services.stripe.webhook_secret'),
                        ]).'.'
                        : 'Stripe is not selected; cash collection remains available.'),
            ],
            'whish' => [
                'ready' => $whishReady,
                'status' => $whishReady ? 'configured' : ($whishEnabled ? 'not_configured' : 'disabled'),
                'detail' => $whishReady
                    ? 'Whish Pay QR and provider-verified callbacks are configured.'
                    : ($whishEnabled
                        ? 'Missing deployment values: '.$this->missingConfiguration([
                            'WHISH_CHANNEL' => config('services.whish.channel'),
                            'WHISH_SECRET' => config('services.whish.secret'),
                            'WHISH_WEBSITE_URL' => config('services.whish.website_url'),
                        ]).'.'
                        : 'Whish Pay is disabled; enable it after merchant acceptance.'),
            ],
        ];
    }

    /** @param array<string, mixed> $values */
    private function missingConfiguration(array $values): string
    {
        return collect($values)
            ->filter(fn (mixed $value): bool => ! $this->configured($value))
            ->keys()
            ->implode(', ');
    }

    private function configured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
