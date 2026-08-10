<?php

use WhishPay\PaymentRequest;
use WhishPay\WhishClient;
use WhishPay\WhishConfig;
use WhishPay\WhishHttpResponse;
use WhishPay\WhishHttpTransport;
use WhishPay\Exceptions\WhishApiException;
use WhishPay\Exceptions\WhishValidationException;

it('creates a Whish payment using the documented JSON contract', function (): void {
    $transport = new class implements WhishHttpTransport
    {
        /** @var array<string, mixed> */
        public array $request = [];

        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            $this->request = compact('method', 'url', 'headers', 'payload', 'timeout');

            return new WhishHttpResponse(200, json_encode([
                'status' => true,
                'data' => ['collectUrl' => 'https://pay.example.test/collect/abc'],
            ], JSON_THROW_ON_ERROR));
        }
    };
    $client = new WhishClient(new WhishConfig('channel', 'secret', 'https://app.example.test'), $transport);

    $result = $client->createPayment(new PaymentRequest(
        amount: '12.50',
        currency: 'USD',
        invoice: 'INV-100',
        externalId: '123456789',
        successCallbackUrl: 'https://app.example.test/success',
        failureCallbackUrl: 'https://app.example.test/failure',
        successRedirectUrl: 'https://app.example.test/redirect/success',
        failureRedirectUrl: 'https://app.example.test/redirect/failure',
    ));

    expect($result->collectUrl)->toBe('https://pay.example.test/collect/abc')
        ->and($transport->request['method'])->toBe('POST')
        ->and($transport->request['url'])->toBe(WhishConfig::SANDBOX_BASE_URL.'/payment/whish')
        ->and($transport->request['headers']['secret'])->toBe('secret')
        ->and($transport->request['payload']['amount'])->toBe('12.50')
        ->and($transport->request['payload']['externalId'])->toBe(123456789);
});

it('requires the callback to use a supported positive payment payload', function (): void {
    expect(fn (): PaymentRequest => new PaymentRequest(
        amount: '0.00',
        currency: 'EUR',
        invoice: 'INV-100',
        externalId: '0',
        successCallbackUrl: 'not-a-url',
        failureCallbackUrl: 'not-a-url',
        successRedirectUrl: 'not-a-url',
        failureRedirectUrl: 'not-a-url',
    ))->toThrow(WhishValidationException::class);
});

it('raises a provider exception instead of accepting a rejected API response', function (): void {
    $transport = new class implements WhishHttpTransport
    {
        public function send(string $method, string $url, array $headers, ?array $payload, int $timeout): WhishHttpResponse
        {
            return new WhishHttpResponse(200, '{"status":false,"code":"DECLINED","dialog":"Declined"}');
        }
    };
    $client = new WhishClient(new WhishConfig('channel', 'secret', 'https://app.example.test'), $transport);

    expect(fn () => $client->getPaymentStatus('123456789', 'USD'))
        ->toThrow(WhishApiException::class, 'Declined');
});
