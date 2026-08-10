<?php

declare(strict_types=1);

namespace WhishPay;

use WhishPay\Exceptions\WhishValidationException;

final readonly class PaymentRequest
{
    public string $amount;
    public string $currency;
    public string $invoice;
    public string $externalId;
    public string $successCallbackUrl;
    public string $failureCallbackUrl;
    public string $successRedirectUrl;
    public string $failureRedirectUrl;

    public function __construct(
        string|int $amount,
        string $currency,
        string $invoice,
        string|int $externalId,
        string $successCallbackUrl,
        string $failureCallbackUrl,
        string $successRedirectUrl,
        string $failureRedirectUrl,
    ) {
        $this->amount = self::decimal($amount);
        $this->currency = strtoupper(trim($currency));
        $this->invoice = trim($invoice);
        $this->externalId = (string) $externalId;
        $this->successCallbackUrl = trim($successCallbackUrl);
        $this->failureCallbackUrl = trim($failureCallbackUrl);
        $this->successRedirectUrl = trim($successRedirectUrl);
        $this->failureRedirectUrl = trim($failureRedirectUrl);

        if (! in_array($this->currency, ['USD', 'LBP', 'AED'], true)) {
            throw new WhishValidationException('Whish supports USD, LBP, and AED payments.');
        }
        if ($this->invoice === '') {
            throw new WhishValidationException('A Whish invoice identifier is required.');
        }
        if (preg_match('/^[1-9][0-9]*$/', $this->externalId) !== 1) {
            throw new WhishValidationException('Whish external IDs must be positive integers.');
        }
        foreach ([$this->successCallbackUrl, $this->failureCallbackUrl, $this->successRedirectUrl, $this->failureRedirectUrl] as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new WhishValidationException('Whish callback and redirect URLs must be valid URLs.');
            }
        }
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'invoice' => $this->invoice,
            'externalId' => (int) $this->externalId,
            'successCallbackUrl' => $this->successCallbackUrl,
            'failureCallbackUrl' => $this->failureCallbackUrl,
            'successRedirectUrl' => $this->successRedirectUrl,
            'failureRedirectUrl' => $this->failureRedirectUrl,
        ];
    }

    private static function decimal(string|int $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/', $value) !== 1) {
            throw new WhishValidationException('Whish payment amounts must be positive decimal values.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (ltrim($whole.$fraction, '0') === '') {
            throw new WhishValidationException('Whish payment amounts must be positive decimal values.');
        }

        return $value;
    }
}
