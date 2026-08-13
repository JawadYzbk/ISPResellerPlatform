<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Money\ExchangeRateProvider;
use App\Domain\Payments\WhishClientFactory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final readonly class CheckProviderConnectivity implements Action
{
    public function __construct(
        private ExchangeRateProvider $exchangeRates,
        private WhishClientFactory $whish,
    ) {}

    /** @return array<string, array{status: string, detail: string}> */
    public function handle(): array
    {
        return [
            'frankfurter' => $this->checkFrankfurter(),
            'stripe' => $this->checkStripe(),
            'whish' => $this->checkWhish(),
            'whatsapp_web' => $this->checkWhatsAppWeb(),
        ];
    }

    /** @return array{status: string, detail: string} */
    private function checkFrankfurter(): array
    {
        if (! (bool) config('services.frankfurter.enabled', false)) {
            return $this->disabled('Frankfurter synchronization is disabled.');
        }

        $quote = collect((array) config('services.frankfurter.quotes', ['EUR']))
            ->map(fn (mixed $currency): string => strtoupper(trim((string) $currency)))
            ->first(fn (string $currency): bool => $currency !== 'USD' && $currency !== '');

        try {
            $quotes = $this->exchangeRates->fetch('USD', [$quote ?: 'EUR']);

            return $quotes === []
                ? $this->failed('Frankfurter returned no usable quote.')
                : $this->ready('Frankfurter returned a live USD quote.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('Frankfurter could not be reached or returned an invalid response.');
        }
    }

    /** @return array{status: string, detail: string} */
    private function checkStripe(): array
    {
        if ((string) config('services.payments.driver', 'null') !== 'stripe') {
            return $this->disabled('Stripe is not the selected online payment driver.');
        }

        $missing = collect(['secret', 'publishable_key', 'webhook_secret'])
            ->filter(fn (string $key): bool => ! filled(config('services.stripe.'.$key)))
            ->values()
            ->all();
        if ($missing !== []) {
            return $this->notConfigured('Stripe configuration is missing: '.implode(', ', $missing).'.');
        }

        $timeout = max(1, (int) config('services.stripe.timeout', 15));

        try {
            $response = Http::withBasicAuth((string) config('services.stripe.secret'), '')
                ->acceptJson()
                ->connectTimeout(min(2, $timeout))
                ->timeout($timeout)
                ->get(rtrim((string) config('services.stripe.endpoint', 'https://api.stripe.com'), '/').'/v1/account');

            return $response->successful()
                ? $this->ready('Stripe account API accepted the configured credentials.')
                : $this->failed('Stripe account API returned HTTP '.$response->status().'.');
        } catch (ConnectionException) {
            return $this->failed('Stripe account API could not be reached.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('Stripe account probe failed.');
        }
    }

    /** @return array{status: string, detail: string} */
    private function checkWhish(): array
    {
        if (! (bool) config('services.whish.enabled', false)) {
            return $this->disabled('Whish Pay is disabled.');
        }

        try {
            $this->whish->make()->getBalance();

            return $this->ready('Whish account endpoint accepted the configured credentials.');
        } catch (Throwable $exception) {
            report($exception);
            $detail = Str::limit($exception->getMessage(), 160, '');

            return $this->failed($detail !== '' ? $detail : 'Whish account probe failed.');
        }
    }

    /** @return array{status: string, detail: string} */
    private function checkWhatsAppWeb(): array
    {
        if ((string) config('services.whatsapp.mode', 'cloud') !== 'web' || ! (bool) config('services.whatsapp.web.enabled', false)) {
            return $this->disabled('WhatsApp Web.js is not the selected provider.');
        }

        if (! filled(config('services.whatsapp.web.token'))) {
            return $this->notConfigured('WhatsApp Web.js bridge token is missing.');
        }

        try {
            $response = Http::withToken((string) config('services.whatsapp.web.token'))
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(5)
                ->get(rtrim((string) config('services.whatsapp.web.endpoint', 'http://whatsapp-web:3001'), '/').'/health');

            return $response->successful()
                ? $this->ready('WhatsApp Web.js bridge health endpoint is reachable.')
                : $this->failed('WhatsApp Web.js bridge returned HTTP '.$response->status().'.');
        } catch (ConnectionException) {
            return $this->failed('WhatsApp Web.js bridge could not be reached.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('WhatsApp Web.js bridge probe failed.');
        }
    }

    /** @return array{status: string, detail: string} */
    private function disabled(string $detail): array
    {
        return ['status' => 'disabled', 'detail' => $detail];
    }

    /** @return array{status: string, detail: string} */
    private function notConfigured(string $detail): array
    {
        return ['status' => 'not_configured', 'detail' => $detail];
    }

    /** @return array{status: string, detail: string} */
    private function ready(string $detail): array
    {
        return ['status' => 'ready', 'detail' => $detail];
    }

    /** @return array{status: string, detail: string} */
    private function failed(string $detail): array
    {
        return ['status' => 'failed', 'detail' => $detail];
    }
}
