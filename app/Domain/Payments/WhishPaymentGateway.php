<?php

namespace App\Domain\Payments;

use App\Models\Currency;
use App\Models\PaymentAttempt;
use WhishPay\PaymentRequest;
use WhishPay\PaymentResponse;
use WhishPay\PaymentStatus;

final readonly class WhishPaymentGateway
{
    public function __construct(private WhishClientFactory $clients) {}

    public function create(PaymentAttempt $attempt): PaymentResponse
    {
        $successCallback = $this->url('success_callback_url', 'api.webhooks.payments.whish.success');
        $failureCallback = $this->url('failure_callback_url', 'api.webhooks.payments.whish.failure');
        $successRedirect = $this->url('success_redirect_url', 'api.webhooks.payments.whish.success');
        $failureRedirect = $this->url('failure_redirect_url', 'api.webhooks.payments.whish.failure');

        return $this->clients->make()->createPayment(new PaymentRequest(
            amount: $this->minorToDecimal($attempt->amount, $attempt->currency),
            currency: strtoupper($attempt->currency),
            invoice: $attempt->invoice_reference,
            externalId: $attempt->external_id,
            successCallbackUrl: $successCallback,
            failureCallbackUrl: $failureCallback,
            successRedirectUrl: $successRedirect,
            failureRedirectUrl: $failureRedirect,
        ));
    }

    public function status(PaymentAttempt $attempt): PaymentStatus
    {
        return $this->clients->make()->getPaymentStatus($attempt->external_id, $attempt->currency);
    }

    public function amountAsDecimal(PaymentAttempt $attempt): string
    {
        return $this->minorToDecimal($attempt->amount, $attempt->currency);
    }

    public function matchesStatus(PaymentAttempt $attempt, PaymentStatus $status): bool
    {
        $currencyMatches = $status->currency === null
            || strtoupper($status->currency) === strtoupper($attempt->currency);
        $amountMatches = $status->amount === null || $this->matchesAmount($attempt, $status->amount);

        return $currencyMatches && $amountMatches;
    }

    private function matchesAmount(PaymentAttempt $attempt, string $providerAmount): bool
    {
        if (preg_match('/^\d+(?:\.\d+)?$/', $providerAmount) !== 1) {
            return false;
        }

        return $this->canonicalDecimal($this->amountAsDecimal($attempt)) === $this->canonicalDecimal($providerAmount);
    }

    private function url(string $configKey, string $routeName): string
    {
        $configured = config('services.whish.'.$configKey);
        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_URL) !== false) {
            return $configured;
        }

        return route($routeName);
    }

    private function minorToDecimal(int $amount, string $currency): string
    {
        $digits = Currency::query()->where('code', strtoupper($currency))->value('decimal_digits');
        $digits = is_numeric($digits) ? (int) $digits : (strtoupper($currency) === 'LBP' ? 0 : 2);
        if ($digits === 0) {
            return (string) $amount;
        }

        $factor = 10 ** $digits;
        $whole = intdiv($amount, $factor);
        $fraction = str_pad((string) ($amount % $factor), $digits, '0', STR_PAD_LEFT);

        return $whole.'.'.$fraction;
    }

    private function canonicalDecimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        return $whole.($fraction === '' ? '' : '.'.$fraction);
    }
}
